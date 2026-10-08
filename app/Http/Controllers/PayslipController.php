<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayslipController extends Controller
{
    public function index(Request $request): View
    {
        $payslips = Payslip::with('period')
            ->where('user_id', $request->user()->id)
            ->whereHas('period', fn ($q) => $q->where('status', 'locked'))
            ->latest('id')
            ->paginate(12);

        return view('payslips.index', compact('payslips'));
    }

    public function show(Request $request, Payslip $payslip): View
    {
        $user = $request->user();
        $payslip->load(['period', 'user.department', 'items']);

        $isOwner = $payslip->user_id === $user->id && $payslip->period->isLocked();
        abort_unless($isOwner || ($user->can('payroll.manage') && $user->canSee($payslip->user)), 403);

        return view('payslips.show', compact('payslip'));
    }
}
