<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\Csv;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index(): View
    {
        $periods = PayrollPeriod::withCount('payslips')
            ->withSum('payslips', 'net_pay')
            ->orderByDesc('start_date')
            ->paginate(12);

        $next = PayrollPeriod::max('end_date');
        $suggested = $next ? Carbon::parse($next)->addDay()->startOfMonth() : now()->startOfMonth();

        return view('payroll.index', compact('periods', 'suggested'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $overlap = PayrollPeriod::whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'El período se cruza con otro período existente.']);
        }

        $period = PayrollPeriod::create($data + ['status' => 'draft']);

        return redirect()->route('payroll.show', $period)->with('status', 'Período creado. Genera la nómina cuando la asistencia esté completa.');
    }

    public function show(PayrollPeriod $period): View
    {
        $payslips = $period->payslips()->with('user.department')->get()->sortBy('user.name');

        return view('payroll.show', [
            'period' => $period,
            'payslips' => $payslips,
            'totals' => [
                'gross' => $payslips->sum('gross_pay'),
                'deductions' => $payslips->sum('total_deductions'),
                'net' => $payslips->sum('net_pay'),
                'overtime' => $payslips->sum('overtime_hours'),
            ],
        ]);
    }

    public function generate(PayrollPeriod $period, PayrollService $payroll): RedirectResponse
    {
        try {
            $count = $payroll->generate($period);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Nómina generada: {$count} recibo(s).");
    }

    public function lock(Request $request, PayrollPeriod $period, PayrollService $payroll): RedirectResponse
    {
        try {
            $payroll->lock($period, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Período bloqueado: la asistencia queda cerrada y las cuotas de anticipos se aplicaron.');
    }

    public function unlock(Request $request, PayrollPeriod $period, PayrollService $payroll): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Solo un administrador puede desbloquear un período.');
        abort_unless($period->isLocked(), 422);

        $payroll->unlock($period);

        return back()->with('status', 'Período desbloqueado.');
    }

    public function destroy(PayrollPeriod $period): RedirectResponse
    {
        abort_if($period->isLocked(), 422, 'No se puede eliminar un período bloqueado.');
        $period->delete();

        return redirect()->route('payroll.index')->with('status', 'Período eliminado.');
    }

    public function export(PayrollPeriod $period): StreamedResponse
    {
        $rows = $period->payslips()->with(['user.department', 'items'])->get()->sortBy('user.name')->map(fn ($p) => [
            $p->user->employee_code, $p->user->name, $p->user->department?->name, User::PAY_TYPES[$p->pay_type] ?? $p->pay_type,
            $p->days_worked, $p->regular_hours, $p->overtime_hours, $p->night_hours, $p->holiday_hours,
            $p->paid_leave_days, $p->unpaid_days, $p->late_count, $p->gross_pay, $p->total_deductions, $p->net_pay,
            $p->user->bank_account,
        ]);

        return Csv::download("nomina-{$period->start_date->format('Y-m-d')}.csv", [
            'Código', 'Empleado', 'Departamento', 'Tipo de pago', 'Días', 'Horas ord.', 'Horas extra', 'Horas noct.',
            'Horas desc./fest.', 'Permisos pagados', 'Días no pagados', 'Tardes', 'Devengado', 'Deducciones', 'Neto', 'Cuenta bancaria',
        ], $rows);
    }
}
