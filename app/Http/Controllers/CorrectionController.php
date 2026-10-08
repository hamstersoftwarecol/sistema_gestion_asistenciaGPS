<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Notifications\HrAlert;
use App\Services\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CorrectionController extends Controller
{
    public function index(Request $request): View
    {
        $corrections = AttendanceCorrection::with('reviewer')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return view('corrections.index', compact('corrections'));
    }

    public function create(Request $request): View
    {
        $date = $request->query('date', today()->toDateString());
        $attendance = Attendance::where('user_id', $request->user()->id)->whereDate('date', $date)->first();

        return view('corrections.create', compact('date', 'attendance'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'type' => ['required', Rule::in(array_keys(AttendanceCorrection::TYPES))],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $needsIn = in_array($data['type'], ['missed_check_in', 'regularization'], true);
        $needsOut = in_array($data['type'], ['missed_check_out', 'regularization'], true);

        if (($needsIn && empty($data['check_in'])) || ($needsOut && empty($data['check_out']))
            || ($data['type'] === 'wrong_time' && empty($data['check_in']) && empty($data['check_out']))) {
            throw ValidationException::withMessages(['check_in' => 'Indica las horas que corresponden al tipo de corrección.']);
        }

        $this->ensureNotLocked($data['date']);

        $user = $request->user();
        [$in, $out] = $this->times($data['date'], $data['check_in'] ?? null, $data['check_out'] ?? null);

        $exists = AttendanceCorrection::where('user_id', $user->id)->whereDate('date', $data['date'])->where('status', 'pending')->exists();
        if ($exists) {
            throw ValidationException::withMessages(['date' => 'Ya tienes una corrección pendiente para esta fecha.']);
        }

        $correction = AttendanceCorrection::create([
            'user_id' => $user->id,
            'attendance_id' => Attendance::where('user_id', $user->id)->whereDate('date', $data['date'])->value('id'),
            'date' => $data['date'],
            'type' => $data['type'],
            'requested_check_in' => $in,
            'requested_check_out' => $out,
            'reason' => $data['reason'],
        ]);

        $this->notifyApprovers($user, $correction);

        return redirect()->route('corrections.index')->with('status', 'Solicitud de corrección enviada para aprobación.');
    }

    public function approvals(Request $request): View
    {
        $viewer = $request->user();
        $team = User::visibleTo($viewer)->pluck('id');

        $pending = AttendanceCorrection::with(['user.department', 'attendance.site'])
            ->whereIn('user_id', $team)
            ->where('status', 'pending')
            ->oldest('date')
            ->get();

        $reviewed = AttendanceCorrection::with(['user', 'reviewer'])
            ->whereIn('user_id', $team)
            ->where('status', '!=', 'pending')
            ->latest('reviewed_at')
            ->limit(20)
            ->get();

        return view('corrections.approvals', compact('pending', 'reviewed'));
    }

    public function approve(Request $request, AttendanceCorrection $correction, AttendanceCalculator $calculator): RedirectResponse
    {
        $this->authorizeReview($correction->user);
        abort_unless($correction->status === 'pending', 422, 'La solicitud ya fue revisada.');
        $this->ensureNotLocked($correction->date);

        $note = $request->input('review_note');

        DB::transaction(function () use ($correction, $calculator, $request, $note) {
            $row = Attendance::firstOrNew(['user_id' => $correction->user_id, 'date' => $correction->date->toDateString()]);

            if (! $row->exists) {
                $row->source = 'correction';
            }

            if ($correction->requested_check_in) {
                $row->check_in_at = $correction->requested_check_in;
            }
            if ($correction->requested_check_out) {
                $row->check_out_at = $correction->requested_check_out;
            }
            if (in_array($row->status, ['absent', 'missed_punch', null], true) && $row->check_out_at) {
                $row->status = 'present';
            }

            $row->regularized = true;
            $row->marked_by = $request->user()->id;
            $row->notes = trim(($row->notes ? $row->notes."\n" : '').'Corrección aprobada: '.$correction->reason);
            $row->setRelation('user', $correction->user);
            $calculator->recalculate($row)->save();

            $correction->update([
                'attendance_id' => $row->id,
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
        });

        $correction->user->notify(new HrAlert(
            'Corrección aprobada',
            'Tu corrección del '.$correction->date->format('d/m/Y').' fue aprobada.',
            route('my-attendance.history', ['month' => $correction->date->format('Y-m')]),
            'success',
        ));

        return back()->with('status', 'Corrección aprobada y asistencia regularizada.');
    }

    public function reject(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $this->authorizeReview($correction->user);
        abort_unless($correction->status === 'pending', 422, 'La solicitud ya fue revisada.');

        $data = $request->validate(['review_note' => ['required', 'string', 'max:500']]);

        $correction->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        $correction->user->notify(new HrAlert(
            'Corrección rechazada',
            'Tu corrección del '.$correction->date->format('d/m/Y').' fue rechazada: '.$data['review_note'],
            route('corrections.index'),
            'danger',
        ));

        return back()->with('status', 'Corrección rechazada.');
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function times(string $date, ?string $in, ?string $out): array
    {
        $checkIn = $in ? Carbon::parse("{$date} {$in}") : null;
        $checkOut = $out ? Carbon::parse("{$date} {$out}") : null;

        // Salida anterior a la entrada: turno que cruza la medianoche.
        if ($checkIn && $checkOut && $checkOut->lessThanOrEqualTo($checkIn)) {
            $checkOut->addDay();
        }

        return [$checkIn, $checkOut];
    }

    protected function notifyApprovers(User $user, AttendanceCorrection $correction): void
    {
        $approvers = collect([$user->manager, $user->department?->manager])->filter()->unique('id')
            ->reject(fn ($u) => $u->id === $user->id);

        $approvers->each(fn (User $a) => $a->notify(new HrAlert(
            'Nueva solicitud de corrección',
            "{$user->name} solicita corregir su asistencia del {$correction->date->format('d/m/Y')}.",
            route('corrections.approvals'),
        )));
    }
}
