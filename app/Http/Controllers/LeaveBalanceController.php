<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);
        $types = LeaveType::active()->where('annual_quota', '>', 0)->orderBy('name')->get();

        $employees = User::visibleTo($request->user())
            ->active()
            ->when($request->query('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->orderBy('name')
            ->get();

        $balances = LeaveBalance::where('year', $year)
            ->whereIn('user_id', $employees->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($items) => $items->keyBy('leave_type_id'));

        return view('leave-balances.index', [
            'year' => $year,
            'types' => $types,
            'employees' => $employees,
            'balances' => $balances,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, LeaveBalance $balance): RedirectResponse
    {
        $this->authorizeEmployee($balance->user);

        $balance->update($request->validate([
            'allocated' => ['required', 'numeric', 'min:0', 'max:365'],
            'carried_forward' => ['required', 'numeric', 'min:0', 'max:365'],
            'used' => ['required', 'numeric', 'min:0', 'max:365'],
        ]));

        return back()->with('status', 'Saldo actualizado.');
    }

    public function store(Request $request, LeaveService $leave): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'leave_type_id' => ['required', 'exists:leave_types,id'], 'year' => ['required', 'integer']]);
        $user = User::findOrFail($data['user_id']);
        $this->authorizeEmployee($user);

        $leave->balanceFor($user, LeaveType::findOrFail($data['leave_type_id']), (int) $data['year']);

        return back()->with('status', 'Saldo creado con la cuota anual del tipo de permiso.');
    }

    public function allocate(Request $request, LeaveService $leave): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100']]);
        $count = $leave->allocateYear((int) $data['year']);

        return back()->with('status', "Cuotas {$data['year']} asignadas ({$count} saldos, con arrastre del año anterior cuando aplica).");
    }
}
