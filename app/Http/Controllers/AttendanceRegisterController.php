<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\AttendanceCalculator;
use App\Services\WorkCalendar;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Registro de asistencia: marcado masivo por fecha y edición individual de una fila.
 */
class AttendanceRegisterController extends Controller
{
    public function index(Request $request, WorkCalendar $calendar): View
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $viewer = $request->user();

        $employees = User::visibleTo($viewer)
            ->active()
            ->with(['department', 'shift', 'site'])
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', $date->toDateString()))
            ->when($request->query('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('employee_code', 'like', "%{$term}%")))
            ->orderBy('name')
            ->get();

        $rows = Attendance::with(['site', 'shift'])
            ->whereIn('user_id', $employees->pluck('id'))
            ->whereDate('date', $date->toDateString())
            ->get()
            ->keyBy('user_id');

        $schedules = $employees->mapWithKeys(fn (User $u) => [$u->id => $calendar->scheduleFor($u, $date)]);
        $departments = Department::orderBy('name')->get();
        $holiday = $calendar->holiday($date);

        return view('register.index', compact('date', 'employees', 'rows', 'schedules', 'departments', 'holiday'));
    }

    public function store(Request $request, AttendanceCalculator $calculator, WorkCalendar $calendar): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'rows' => ['array'],
            'rows.*.apply' => ['nullable'],
            'rows.*.status' => ['nullable', Rule::in(['present', 'absent', 'half_day'])],
            'rows.*.check_in' => ['nullable', 'date_format:H:i'],
            'rows.*.check_out' => ['nullable', 'date_format:H:i'],
            'rows.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $date = Carbon::parse($data['date']);
        $this->ensureNotLocked($date);

        $saved = 0;
        $skipped = 0;

        foreach ($data['rows'] ?? [] as $userId => $input) {
            if (empty($input['apply']) || empty($input['status'])) {
                continue;
            }

            $employee = User::findOrFail($userId);
            $this->authorizeEmployee($employee);

            $row = Attendance::firstOrNew(['user_id' => $employee->id, 'date' => $date->toDateString()]);
            $row->setRelation('user', $employee);

            // Las marcaciones hechas desde la app (selfie + GPS) no se sobrescriben en bloque.
            if ($row->exists && in_array($row->source, ['app', 'offline'], true) && $input['status'] === 'absent') {
                $skipped++;

                continue;
            }

            $schedule = $calendar->scheduleFor($employee, $date);
            $shift = $schedule['shift'];

            $row->fill([
                'shift_id' => $row->shift_id ?? $shift?->id,
                'site_id' => $row->site_id ?? $schedule['site']?->id,
                'source' => $row->exists ? $row->source : 'bulk',
                'marked_by' => $request->user()->id,
                'notes' => $input['notes'] ?? $row->notes,
            ]);

            if ($input['status'] === 'absent') {
                $row->fill(['status' => 'absent', 'check_in_at' => null, 'check_out_at' => null, 'leave_request_id' => null, 'leave_type_id' => null]);
            } else {
                [$start, $end] = $shift ? $shift->windowFor($date) : [$date->copy()->setTime(8, 0), $date->copy()->setTime(17, 0)];

                if ($input['status'] === 'half_day' && empty($input['check_out'])) {
                    $end = $start->copy()->addMinutes((int) setting('half_day_minutes'));
                }

                $in = ! empty($input['check_in']) ? Carbon::parse($date->toDateString().' '.$input['check_in']) : ($row->check_in_at ?? $start);
                $out = ! empty($input['check_out']) ? Carbon::parse($date->toDateString().' '.$input['check_out']) : ($row->check_out_at ?? $end);

                if ($out->lessThanOrEqualTo($in)) {
                    $out->addDay();
                }

                $row->fill(['check_in_at' => $in, 'check_out_at' => $out->greaterThan(now()) ? null : $out, 'status' => 'present']);
            }

            $calculator->recalculate($row);

            if ($input['status'] === 'half_day') {
                $row->status = 'half_day';
            }

            $row->save();
            $saved++;
        }

        $message = "Asistencia guardada para {$saved} empleado(s).";
        if ($skipped) {
            $message .= " {$skipped} fila(s) con marcación por app no se marcaron como ausentes.";
        }

        return back()->with('status', $message);
    }

    public function edit(Request $request, User $user, string $date): View
    {
        $this->authorizeEmployee($user);
        $day = Carbon::parse($date);

        $attendance = Attendance::with(['site', 'checkOutSite', 'shift', 'markedBy', 'leaveType'])
            ->where('user_id', $user->id)
            ->whereDate('date', $day->toDateString())
            ->first() ?? new Attendance(['user_id' => $user->id, 'date' => $day, 'status' => 'absent']);

        return view('register.edit', [
            'employee' => $user,
            'day' => $day,
            'attendance' => $attendance,
            'shifts' => Shift::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'leaveTypes' => LeaveType::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user, string $date, AttendanceCalculator $calculator): RedirectResponse
    {
        $this->authorizeEmployee($user);
        $day = Carbon::parse($date);
        $this->ensureNotLocked($day);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Attendance::STATUSES))],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date', 'after:check_in_at'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'leave_type_id' => ['nullable', 'required_if:status,on_leave', 'exists:leave_types,id'],
            'approved_overtime_minutes' => ['nullable', 'integer', 'min:0'],
            'overtime_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = Attendance::firstOrNew(['user_id' => $user->id, 'date' => $day->toDateString()]);
        $row->setRelation('user', $user);

        if (! $row->exists) {
            $row->source = 'manual';
        }

        $hasTimes = ! empty($data['check_in_at']);

        $row->fill([
            'check_in_at' => $hasTimes ? $data['check_in_at'] : null,
            'check_out_at' => $hasTimes ? ($data['check_out_at'] ?? null) : null,
            'shift_id' => $data['shift_id'] ?? null,
            'site_id' => $data['site_id'] ?? $row->site_id,
            'status' => $data['status'],
            'leave_type_id' => $data['status'] === 'on_leave' ? $data['leave_type_id'] : null,
            'notes' => $data['notes'] ?? null,
            'marked_by' => $request->user()->id,
        ]);

        $calculator->recalculate($row);

        if (! $hasTimes) {
            $row->status = $data['status'];
        } elseif (in_array($data['status'], ['half_day', 'missed_punch'], true)) {
            $row->status = $data['status'];
        }

        if ($row->overtime_minutes > 0 && ! empty($data['overtime_status'])) {
            $row->overtime_status = $data['overtime_status'];
            $row->approved_overtime_minutes = match ($data['overtime_status']) {
                'approved' => min($row->overtime_minutes, (int) ($data['approved_overtime_minutes'] ?? $row->overtime_minutes)),
                default => 0,
            };
            $row->overtime_reviewed_by = $request->user()->id;
        }

        $row->save();

        return redirect()
            ->route('register.index', ['date' => $day->toDateString()])
            ->with('status', "Asistencia de {$user->name} actualizada.");
    }

    public function destroy(User $user, string $date): RedirectResponse
    {
        $this->authorizeEmployee($user);
        $this->ensureNotLocked($date);

        Attendance::where('user_id', $user->id)->whereDate('date', $date)->delete();

        return redirect()->route('register.index', ['date' => $date])->with('status', 'Fila de asistencia eliminada.');
    }
}
