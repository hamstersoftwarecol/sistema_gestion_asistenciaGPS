<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Regularización y aprobación de horas extras calculadas a partir de la asistencia.
 */
class OvertimeController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $team = User::visibleTo($request->user())->pluck('id');

        $rows = Attendance::with(['user.department', 'shift', 'site'])
            ->whereIn('user_id', $team)
            ->where('overtime_minutes', '>', 0)
            ->when($status !== 'all', fn ($q) => $q->where('overtime_status', $status))
            ->orderByDesc('date')
            ->paginate(30)
            ->withQueryString();

        return view('overtime.index', compact('rows', 'status'));
    }

    public function review(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'minutes' => ['nullable', 'array'],
            'minutes.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $count = 0;

        foreach (Attendance::with('user')->whereIn('id', $data['ids'])->get() as $row) {
            $this->authorizeReview($row->user);
            $this->ensureNotLocked($row->date, 'ids');

            $minutes = (int) ($data['minutes'][$row->id] ?? $row->overtime_minutes);

            $row->update([
                'overtime_status' => $data['decision'],
                'approved_overtime_minutes' => $data['decision'] === 'approved' ? min($minutes, $row->overtime_minutes) : 0,
                'overtime_reviewed_by' => $request->user()->id,
            ]);
            $count++;
        }

        $label = $data['decision'] === 'approved' ? 'aprobadas' : 'rechazadas';

        return back()->with('status', "Horas extra {$label} en {$count} registro(s).");
    }
}
