<?php

namespace App\Http\Controllers;

use App\Models\FinalSettlement;
use App\Models\User;
use App\Services\SettlementService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function index(): View
    {
        return view('settlements.index', [
            'settlements' => FinalSettlement::with(['user', 'creator'])->latest()->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        return view('settlements.create', [
            'employees' => User::visibleTo($request->user())->where('status', '!=', 'terminated')->orderBy('name')->pluck('name', 'id'),
            'selected' => $request->query('user_id'),
        ]);
    }

    public function store(Request $request, SettlementService $service): RedirectResponse
    {
        $data = $this->validated($request);
        $employee = User::findOrFail($data['user_id']);
        $this->authorizeEmployee($employee);

        $computed = $service->compute($employee, Carbon::parse($data['termination_date']), (float) ($data['other_earnings'] ?? 0), (float) ($data['other_deductions'] ?? 0));

        $settlement = FinalSettlement::create(collect($computed)->except('pending_from')->all() + [
            'user_id' => $employee->id,
            'termination_date' => $data['termination_date'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('settlements.show', $settlement)->with('status', 'Liquidación calculada en borrador. Revísala antes de aprobar.');
    }

    public function show(FinalSettlement $settlement): View
    {
        $this->authorizeEmployee($settlement->user);

        return view('settlements.show', ['settlement' => $settlement->load(['user.department', 'creator', 'approver'])]);
    }

    public function recalculate(Request $request, FinalSettlement $settlement, SettlementService $service): RedirectResponse
    {
        $this->authorizeEmployee($settlement->user);
        abort_unless($settlement->status === 'draft', 422, 'Solo se recalculan liquidaciones en borrador.');

        $data = $request->validate([
            'other_earnings' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
        ]);

        $computed = $service->compute($settlement->user, $settlement->termination_date, (float) ($data['other_earnings'] ?? 0), (float) ($data['other_deductions'] ?? 0));
        $settlement->update(collect($computed)->except('pending_from')->all());

        return back()->with('status', 'Liquidación recalculada.');
    }

    public function approve(Request $request, FinalSettlement $settlement, SettlementService $service): RedirectResponse
    {
        $this->authorizeReview($settlement->user);
        abort_unless($settlement->status === 'draft', 422);

        $service->approve($settlement, $request->user());

        return back()->with('status', 'Liquidación aprobada: el empleado quedó retirado y sus anticipos saldados.');
    }

    public function markPaid(FinalSettlement $settlement): RedirectResponse
    {
        $this->authorizeEmployee($settlement->user);
        abort_unless($settlement->status === 'approved', 422);
        $settlement->update(['status' => 'paid']);

        return back()->with('status', 'Liquidación marcada como pagada.');
    }

    public function destroy(FinalSettlement $settlement): RedirectResponse
    {
        abort_unless($settlement->status === 'draft', 422, 'Solo se eliminan borradores.');
        $settlement->delete();

        return redirect()->route('settlements.index')->with('status', 'Borrador eliminado.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'termination_date' => ['required', 'date'],
            'reason' => ['required', Rule::in(array_keys(FinalSettlement::REASONS))],
            'other_earnings' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
