<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Payslip;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $today = today();

        $myToday = Attendance::with('site')->where('user_id', $user->id)->whereDate('date', $today->toDateString())->first();
        $monthRows = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])
            ->get();

        $mine = [
            'present' => $monthRows->whereIn('status', ['present', 'late', 'half_day'])->count(),
            'late' => $monthRows->where('status', 'late')->count(),
            'absent' => $monthRows->where('status', 'absent')->count(),
            'worked' => (int) $monthRows->sum('worked_minutes'),
            'overtime' => (int) $monthRows->sum('approved_overtime_minutes'),
        ];

        $balances = LeaveBalance::with('leaveType')->where('user_id', $user->id)->where('year', $today->year)->get();
        $lastPayslip = Payslip::with('period')
            ->where('user_id', $user->id)
            ->whereHas('period', fn ($q) => $q->where('status', 'locked'))
            ->latest('id')
            ->first();

        $team = null;

        if ($user->can('attendance.view')) {
            $ids = User::visibleTo($user)->active()->pluck('id');
            $rows = Attendance::with(['user', 'site'])->whereIn('user_id', $ids)->whereDate('date', $today->toDateString())->get();
            $reviewIds = $ids->reject(fn ($id) => $id === $user->id);
            $checkedIn = $rows->whereNotNull('check_in_at')->count();
            $onLeave = $rows->where('status', 'on_leave')->count();

            $weekRows = Attendance::whereIn('user_id', $ids)
                ->whereBetween('date', [$today->copy()->subDays(6)->toDateString(), $today->toDateString()])
                ->get()
                ->groupBy(fn ($r) => $r->date->toDateString());

            $team = [
                'total' => $ids->count(),
                'checked_in' => $checkedIn,
                'late' => $rows->where('status', 'late')->count(),
                'on_leave' => $onLeave,
                'out_of_zone' => $rows->filter->isOutOfZone()->count(),
                'not_in' => max(0, $ids->count() - $checkedIn - $onLeave),
                'recent' => $rows->whereNotNull('check_in_at')->sortByDesc(fn ($r) => $r->check_out_at ?? $r->check_in_at)->take(8),
                'pending_corrections' => AttendanceCorrection::where('status', 'pending')->whereIn('user_id', $reviewIds)->count(),
                'pending_leave' => LeaveRequest::where('status', 'pending')->whereIn('user_id', $reviewIds)->count(),
                'pending_overtime' => Attendance::where('overtime_status', 'pending')->whereIn('user_id', $reviewIds)->count(),
                'week' => collect(CarbonPeriod::create($today->copy()->subDays(6), $today))->map(function ($day) use ($weekRows) {
                    $r = $weekRows->get($day->toDateString(), collect());

                    return [
                        'label' => $day->locale('es')->isoFormat('ddd D'),
                        'present' => $r->whereIn('status', ['present', 'half_day'])->count(),
                        'late' => $r->where('status', 'late')->count(),
                        'absent' => $r->whereIn('status', ['absent', 'missed_punch'])->count(),
                        'leave' => $r->where('status', 'on_leave')->count(),
                    ];
                })->values(),
            ];
        }

        return view('dashboard', compact('user', 'myToday', 'mine', 'balances', 'lastPayslip', 'team'));
    }
}
