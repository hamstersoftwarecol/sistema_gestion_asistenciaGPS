<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Informes de RR. HH. Cada informe devuelve columnas y filas planas, de modo que la misma
 * estructura se muestra en pantalla y se exporta a CSV.
 */
class ReportService
{
    public const REPORTS = [
        'summary' => ['Resumen de asistencia', 'Días presentes, retrasos, ausencias, permisos y horas por empleado.'],
        'muster' => ['Lista de asistencia (muster roll)', 'Cuadrícula empleado × día con el código de estado de cada fila.'],
        'late' => ['Retrasos', 'Llegadas tarde fuera del tiempo de gracia.'],
        'overtime' => ['Horas extra', 'Horas extra calculadas, aprobadas y pendientes.'],
        'out_of_zone' => ['Registros fuera de zona', 'Marcaciones fuera de la geocerca del sitio más cercano.'],
        'leave' => ['Permisos', 'Solicitudes de permiso por estado y tipo.'],
        'payroll' => ['Nómina por período', 'Devengos, deducciones y neto de cada recibo.'],
    ];

    public function __construct(protected User $viewer) {}

    /**
     * @param  array{from: string, to: string, department_id?: ?int, site_id?: ?int, user_id?: ?int, period_id?: ?int}  $filters
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, mixed>>, codes?: bool}
     */
    public function run(string $report, array $filters): array
    {
        $from = Carbon::parse($filters['from']);
        $to = Carbon::parse($filters['to']);

        return match ($report) {
            'summary' => $this->summary($from, $to, $filters),
            'muster' => $this->muster($from, $to, $filters),
            'late' => $this->late($from, $to, $filters),
            'overtime' => $this->overtime($from, $to, $filters),
            'out_of_zone' => $this->outOfZone($from, $to, $filters),
            'leave' => $this->leave($from, $to, $filters),
            'payroll' => $this->payroll($filters),
        };
    }

    /**
     * @return Collection<int, User>
     */
    protected function employees(array $filters): Collection
    {
        return User::query()
            ->visibleTo($this->viewer)
            ->with('department')
            ->when($filters['department_id'] ?? null, fn (Builder $q, $id) => $q->where('department_id', $id))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))
            ->where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get();
    }

    protected function rows(Carbon $from, Carbon $to, array $filters): Builder
    {
        return Attendance::query()
            ->with(['user.department', 'site', 'shift'])
            ->whereIn('user_id', $this->employees($filters)->pluck('id'))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['site_id'] ?? null, fn (Builder $q, $id) => $q->where('site_id', $id))
            ->orderBy('date');
    }

    protected function summary(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = $this->rows($from, $to, $filters)->get()->groupBy('user_id');

        $data = $this->employees($filters)->map(function (User $u) use ($rows) {
            $r = $rows->get($u->id, collect());

            return [
                $u->employee_code, $u->name, $u->department?->name,
                $r->whereIn('status', ['present', 'late', 'half_day'])->count(),
                $r->where('status', 'late')->count(),
                $r->where('status', 'half_day')->count(),
                $r->where('status', 'absent')->count(),
                $r->where('status', 'on_leave')->count(),
                $r->where('status', 'missed_punch')->count(),
                round($r->sum('worked_minutes') / 60, 2),
                round($r->sum('approved_overtime_minutes') / 60, 2),
                round($r->sum('night_minutes') / 60, 2),
                $r->filter->isOutOfZone()->count(),
            ];
        })->values()->all();

        return [
            'title' => 'Resumen de asistencia',
            'columns' => ['Código', 'Empleado', 'Departamento', 'Días trabajados', 'Tardes', 'Medios días', 'Ausencias', 'Permisos', 'Omitidas', 'Horas trabajadas', 'Horas extra aprob.', 'Horas nocturnas', 'Fuera de zona'],
            'rows' => $data,
        ];
    }

    protected function muster(Carbon $from, Carbon $to, array $filters): array
    {
        $to = $from->diffInDays($to) > 62 ? $from->copy()->addDays(62) : $to;
        $days = collect(CarbonPeriod::create($from, $to))->map(fn ($d) => $d->copy());
        $rows = $this->rows($from, $to, $filters)->get()->groupBy('user_id');
        $calendar = app(WorkCalendar::class);
        $calendar->preloadHolidays($from, $to);

        $data = $this->employees($filters)->map(function (User $u) use ($rows, $days, $calendar) {
            $byDate = $rows->get($u->id, collect())->keyBy(fn ($r) => $r->date->toDateString());
            $line = [$u->employee_code, $u->name];

            foreach ($days as $day) {
                $row = $byDate->get($day->toDateString());
                $line[] = match (true) {
                    $row !== null => $row->statusCode(),
                    $calendar->holiday($day) !== null => 'F',
                    $calendar->isWeekend($day) => 'D',
                    $day->isFuture() => '',
                    default => '-',
                };
            }

            $line[] = $byDate->filter(fn ($r) => in_array($r->status, ['present', 'late', 'half_day'], true))->count();
            $line[] = $byDate->where('status', 'absent')->count();

            return $line;
        })->values()->all();

        return [
            'title' => 'Lista de asistencia',
            'columns' => array_merge(['Código', 'Empleado'], $days->map(fn ($d) => $d->format('d/m'))->all(), ['Pres.', 'Aus.']),
            'rows' => $data,
            'codes' => true,
        ];
    }

    protected function late(Carbon $from, Carbon $to, array $filters): array
    {
        $data = $this->rows($from, $to, $filters)->where('late_minutes', '>', 0)->get()->map(fn (Attendance $a) => [
            $a->date->format('Y-m-d'), $a->user->employee_code, $a->user->name, $a->shift?->name,
            $a->shift?->start_time, $a->check_in_at?->format('H:i'), $a->late_minutes, $a->statusLabel(), $a->site?->name,
        ])->all();

        return [
            'title' => 'Retrasos',
            'columns' => ['Fecha', 'Código', 'Empleado', 'Turno', 'Inicio turno', 'Entrada', 'Minutos tarde', 'Estado', 'Sitio'],
            'rows' => $data,
        ];
    }

    protected function overtime(Carbon $from, Carbon $to, array $filters): array
    {
        $data = $this->rows($from, $to, $filters)->where('overtime_minutes', '>', 0)->get()->map(fn (Attendance $a) => [
            $a->date->format('Y-m-d'), $a->user->employee_code, $a->user->name,
            $a->check_in_at?->format('H:i'), $a->check_out_at?->format('H:i'),
            round($a->worked_minutes / 60, 2), round($a->overtime_minutes / 60, 2), round($a->approved_overtime_minutes / 60, 2),
            Attendance::OVERTIME_STATUSES[$a->overtime_status] ?? $a->overtime_status,
        ])->all();

        return [
            'title' => 'Horas extra',
            'columns' => ['Fecha', 'Código', 'Empleado', 'Entrada', 'Salida', 'Horas trabajadas', 'Horas extra', 'Aprobadas', 'Estado'],
            'rows' => $data,
        ];
    }

    protected function outOfZone(Carbon $from, Carbon $to, array $filters): array
    {
        $data = $this->rows($from, $to, $filters)
            ->where(fn ($q) => $q->where('check_in_out_of_zone', true)->orWhere('check_out_out_of_zone', true))
            ->get()
            ->map(fn (Attendance $a) => [
                $a->date->format('Y-m-d'), $a->user->employee_code, $a->user->name, $a->site?->name,
                $a->check_in_at?->format('H:i'), $a->check_in_distance_m, $a->check_in_out_of_zone ? 'Sí' : 'No',
                $a->check_out_at?->format('H:i'), $a->check_out_distance_m, $a->check_out_out_of_zone ? 'Sí' : 'No',
                $a->check_in_lat ? "{$a->check_in_lat},{$a->check_in_lng}" : '',
            ])->all();

        return [
            'title' => 'Registros fuera de zona',
            'columns' => ['Fecha', 'Código', 'Empleado', 'Sitio más cercano', 'Entrada', 'Dist. entrada (m)', 'Fuera (entrada)', 'Salida', 'Dist. salida (m)', 'Fuera (salida)', 'Coordenadas entrada'],
            'rows' => $data,
        ];
    }

    protected function leave(Carbon $from, Carbon $to, array $filters): array
    {
        $data = LeaveRequest::with(['user', 'leaveType', 'reviewer'])
            ->whereIn('user_id', $this->employees($filters)->pluck('id'))
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->orderBy('start_date')
            ->get()
            ->map(fn (LeaveRequest $l) => [
                $l->user->employee_code, $l->user->name, $l->leaveType->name, $l->start_date->format('Y-m-d'),
                $l->end_date->format('Y-m-d'), $l->days, LeaveRequest::STATUSES[$l->status] ?? $l->status, $l->reviewer?->name,
            ])->all();

        return [
            'title' => 'Permisos',
            'columns' => ['Código', 'Empleado', 'Tipo', 'Desde', 'Hasta', 'Días', 'Estado', 'Revisado por'],
            'rows' => $data,
        ];
    }

    protected function payroll(array $filters): array
    {
        $period = PayrollPeriod::find($filters['period_id'] ?? null) ?? PayrollPeriod::orderByDesc('end_date')->first();

        if (! $period) {
            return ['title' => 'Nómina por período', 'columns' => ['Sin períodos de nómina'], 'rows' => []];
        }

        $ids = $this->employees($filters)->pluck('id');

        $data = $period->payslips()->with('user.department')->whereIn('user_id', $ids)->get()->map(fn ($p) => [
            $period->name, $p->user->employee_code, $p->user->name, $p->user->department?->name,
            User::PAY_TYPES[$p->pay_type] ?? $p->pay_type, $p->days_worked, $p->regular_hours, $p->overtime_hours,
            $p->night_hours, $p->gross_pay, $p->total_deductions, $p->net_pay,
        ])->all();

        return [
            'title' => "Nómina — {$period->name}",
            'columns' => ['Período', 'Código', 'Empleado', 'Departamento', 'Tipo de pago', 'Días', 'Horas ord.', 'Horas extra', 'Horas noct.', 'Devengado', 'Deducciones', 'Neto'],
            'rows' => $data,
        ];
    }
}
