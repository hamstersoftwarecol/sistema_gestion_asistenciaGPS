<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\HrAlert;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('leave-requests.index', [
            'requests' => LeaveRequest::with(['leaveType', 'reviewer'])->where('user_id', $user->id)->latest()->paginate(15),
            'balances' => LeaveBalance::with('leaveType')->where('user_id', $user->id)->where('year', now()->year)->get(),
        ]);
    }

    public function create(): View
    {
        return view('leave-requests.create', ['types' => LeaveType::active()->orderBy('name')->get()]);
    }

    public function store(Request $request, LeaveService $leave): RedirectResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $type = LeaveType::findOrFail($data['leave_type_id']);

        if ($type->requires_attachment && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages(['attachment' => 'Este tipo de permiso requiere un soporte adjunto.']);
        }

        $path = $request->file('attachment')?->store('leave-attachments', 'local');
        $user = $request->user();

        $leaveRequest = $leave->request($user, $type, Carbon::parse($data['start_date']), Carbon::parse($data['end_date']), $data['reason'] ?? null, $path);

        collect([$user->manager, $user->department?->manager])->filter()->unique('id')
            ->reject(fn ($u) => $u->id === $user->id)
            ->each(fn (User $u) => $u->notify(new HrAlert(
                'Nueva solicitud de permiso',
                "{$user->name} solicita {$type->name} del {$leaveRequest->start_date->format('d/m')} al {$leaveRequest->end_date->format('d/m/Y')} ({$leaveRequest->days} días).",
                route('leave-requests.approvals'),
            )));

        return redirect()->route('leave-requests.index')->with('status', "Solicitud enviada: {$leaveRequest->days} día(s) hábiles.");
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, LeaveService $leave): RedirectResponse
    {
        $user = $request->user();
        $isOwner = $leaveRequest->user_id === $user->id;

        abort_unless($isOwner || ($user->can('leave.approve') && $user->canSee($leaveRequest->user)), 403);
        abort_unless(in_array($leaveRequest->status, ['pending', 'approved'], true), 422, 'La solicitud no se puede cancelar.');
        abort_if($isOwner && $leaveRequest->status === 'approved' && $leaveRequest->start_date->isPast(), 422, 'El permiso ya inició; pide a RR. HH. que lo cancele.');

        $leave->cancel($leaveRequest);

        return back()->with('status', 'Permiso cancelado.');
    }

    public function approvals(Request $request): View
    {
        $team = User::visibleTo($request->user())->pluck('id');

        return view('leave-requests.approvals', [
            'pending' => LeaveRequest::with(['user.department', 'leaveType'])->whereIn('user_id', $team)->where('status', 'pending')->oldest('start_date')->get(),
            'recent' => LeaveRequest::with(['user', 'leaveType', 'reviewer'])->whereIn('user_id', $team)->where('status', '!=', 'pending')->latest('updated_at')->limit(20)->get(),
            'balances' => LeaveBalance::whereIn('user_id', $team)->where('year', now()->year)->get()->groupBy('user_id'),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, LeaveService $leave): RedirectResponse
    {
        $this->authorizeReview($leaveRequest->user);
        abort_unless($leaveRequest->status === 'pending', 422, 'La solicitud ya fue revisada.');

        $leave->approve($leaveRequest, $request->user(), $request->input('review_note'));

        $leaveRequest->user->notify(new HrAlert(
            'Permiso aprobado',
            "Tu solicitud de {$leaveRequest->leaveType->name} ({$leaveRequest->start_date->format('d/m')}–{$leaveRequest->end_date->format('d/m/Y')}) fue aprobada.",
            route('leave-requests.index'),
            'success',
        ));

        return back()->with('status', 'Permiso aprobado: saldo descontado y asistencia marcada.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, LeaveService $leave): RedirectResponse
    {
        $this->authorizeReview($leaveRequest->user);
        abort_unless($leaveRequest->status === 'pending', 422, 'La solicitud ya fue revisada.');

        $data = $request->validate(['review_note' => ['required', 'string', 'max:500']]);
        $leave->reject($leaveRequest, $request->user(), $data['review_note']);

        $leaveRequest->user->notify(new HrAlert(
            'Permiso rechazado',
            "Tu solicitud de {$leaveRequest->leaveType->name} fue rechazada: {$data['review_note']}",
            route('leave-requests.index'),
            'danger',
        ));

        return back()->with('status', 'Permiso rechazado.');
    }

    public function attachment(Request $request, LeaveRequest $leaveRequest): StreamedResponse
    {
        $user = $request->user();
        abort_unless($leaveRequest->user_id === $user->id || $user->canSee($leaveRequest->user), 403);
        abort_unless($leaveRequest->attachment && Storage::disk('local')->exists($leaveRequest->attachment), 404);

        return Storage::disk('local')->download($leaveRequest->attachment);
    }
}
