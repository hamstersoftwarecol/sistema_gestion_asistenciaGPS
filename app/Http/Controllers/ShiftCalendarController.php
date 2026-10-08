<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShiftCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $month = Carbon::parse(($request->query('month') ?: now()->format('Y-m')).'-01');
        $days = collect(CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()));

        $employees = User::visibleTo($request->user())
            ->active()
            ->with(['shift', 'site', 'department'])
            ->when($request->query('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->orderBy('name')
            ->get();

        $assignments = ShiftAssignment::with(['shift', 'site'])
            ->whereIn('user_id', $employees->pluck('id'))
            ->whereBetween('date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($items) => $items->keyBy(fn ($a) => $a->date->toDateString()));

        $holidays = Holiday::whereBetween('date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->get()
            ->keyBy(fn ($h) => $h->date->toDateString());

        return view('shift-calendar.index', [
            'month' => $month,
            'days' => $days,
            'employees' => $employees,
            'assignments' => $assignments,
            'holidays' => $holidays,
            'weekendDays' => array_map('intval', (array) setting('weekend_days')),
            'shifts' => Shift::active()->orderBy('start_time')->get(),
            'sites' => Site::active()->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'mode' => ['required', Rule::in(['shift', 'off', 'clear'])],
            'shift_id' => ['nullable', 'required_if:mode,shift', 'exists:shifts,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
        ]);

        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);

        if ($from->diffInDays($to) > 92) {
            throw ValidationException::withMessages(['to' => 'El rango máximo es de 3 meses.']);
        }

        $weekdays = array_map('intval', $data['weekdays'] ?? range(0, 6));
        $count = 0;

        foreach (User::whereIn('id', $data['user_ids'])->get() as $employee) {
            $this->authorizeEmployee($employee);

            foreach (CarbonPeriod::create($from, $to) as $day) {
                if (! in_array($day->dayOfWeek, $weekdays, true)) {
                    continue;
                }

                $key = ['user_id' => $employee->id, 'date' => $day->toDateString()];

                match ($data['mode']) {
                    'clear' => ShiftAssignment::where('user_id', $employee->id)->whereDate('date', $day->toDateString())->delete(),
                    'off' => ShiftAssignment::updateOrCreate($key, ['shift_id' => null, 'site_id' => null, 'is_off' => true, 'assigned_by' => $request->user()->id]),
                    'shift' => ShiftAssignment::updateOrCreate($key, [
                        'shift_id' => $data['shift_id'],
                        'site_id' => $data['site_id'] ?? null,
                        'is_off' => false,
                        'assigned_by' => $request->user()->id,
                    ]),
                };
                $count++;
            }
        }

        return redirect()
            ->route('shift-calendar.index', ['month' => $from->format('Y-m'), 'department_id' => $request->input('department_id')])
            ->with('status', "Calendario actualizado: {$count} día(s) modificados.");
    }
}
