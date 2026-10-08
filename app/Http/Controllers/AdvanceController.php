<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdvanceController extends Controller
{
    public function index(Request $request): View
    {
        $team = User::visibleTo($request->user())->pluck('id');

        $advances = Advance::with(['user', 'approver', 'repayments'])
            ->whereIn('user_id', $team)
            ->when($request->query('status', 'active'), fn ($q, $s) => $s === 'all' ? $q : $q->where('status', $s))
            ->latest('issued_on')
            ->paginate(20)
            ->withQueryString();

        return view('advances.index', [
            'advances' => $advances,
            'employees' => User::visibleTo($request->user())->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', Rule::in(array_keys(Advance::TYPES))],
            'amount' => ['required', 'numeric', 'min:1'],
            'installments' => ['required', 'integer', 'min:1', 'max:60'],
            'issued_on' => ['required', 'date'],
            'deduct_from' => ['required', 'date', 'after_or_equal:issued_on'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->authorizeEmployee(User::findOrFail($data['user_id']));

        Advance::create($data + [
            'installment_amount' => round($data['amount'] / $data['installments'], 2),
            'balance' => $data['amount'],
            'status' => 'active',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('status', (Advance::TYPES[$data['type']]).' registrado. Las cuotas se descontarán en la nómina.');
    }

    public function destroy(Advance $advance): RedirectResponse
    {
        $this->authorizeEmployee($advance->user);

        if ($advance->repayments()->exists()) {
            $advance->update(['status' => 'cancelled']);

            return back()->with('status', 'El registro tiene cuotas pagadas: se anuló el saldo restante.');
        }

        $advance->delete();

        return back()->with('status', 'Registro eliminado.');
    }
}
