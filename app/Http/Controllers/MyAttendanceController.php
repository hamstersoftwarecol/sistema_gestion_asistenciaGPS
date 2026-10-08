<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Site;
use App\Services\PunchService;
use App\Services\WorkCalendar;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MyAttendanceController extends Controller
{
    public function index(Request $request, WorkCalendar $calendar): View
    {
        $user = $request->user();
        $today = Attendance::with(['site', 'checkOutSite', 'shift'])
            ->where('user_id', $user->id)
            ->whereDate('date', today()->toDateString())
            ->first();

        // Turno nocturno abierto desde ayer.
        $open = Attendance::with('site')
            ->where('user_id', $user->id)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->where('check_in_at', '>=', now()->subHours(20))
            ->latest('check_in_at')
            ->first();

        $recent = Attendance::with('site')->where('user_id', $user->id)->latest('date')->limit(7)->get();
        $schedule = $calendar->scheduleFor($user, today());
        $sites = Site::active()->get(['id', 'name', 'latitude', 'longitude', 'radius_m']);

        return view('my-attendance.index', compact('today', 'open', 'recent', 'schedule', 'sites'));
    }

    public function punch(Request $request, PunchService $service): JsonResponse
    {
        $data = $request->validate($this->rules());

        try {
            $result = $service->punch($request->user(), $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->payload($result));
    }

    /**
     * Reproduce la cola de marcaciones guardadas sin conexión, en orden cronológico.
     */
    public function sync(Request $request, PunchService $service): JsonResponse
    {
        $request->validate([
            'punches' => ['required', 'array', 'max:50'],
            'punches.*.client_id' => ['required', 'string', 'max:64'],
        ]);

        $results = [];
        $punches = collect($request->input('punches'))->sortBy('captured_at');

        foreach ($punches as $punch) {
            $validator = validator($punch, $this->rules());

            if ($validator->fails()) {
                $results[] = ['client_id' => $punch['client_id'], 'ok' => false, 'discard' => true, 'message' => $validator->errors()->first()];

                continue;
            }

            try {
                $result = $service->punch($request->user(), $validator->validated() + ['offline' => true]);
                $results[] = ['client_id' => $punch['client_id'], 'ok' => true] + $this->payload($result);
            } catch (RuntimeException $e) {
                $results[] = ['client_id' => $punch['client_id'], 'ok' => false, 'discard' => true, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function token(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function history(Request $request): View
    {
        $month = Carbon::parse(($request->query('month') ?: now()->format('Y-m')).'-01');

        $rows = Attendance::with(['site', 'checkOutSite', 'shift', 'leaveType'])
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('date')
            ->get();

        $summary = [
            'present' => $rows->whereIn('status', ['present', 'late', 'half_day'])->count(),
            'late' => $rows->where('status', 'late')->count(),
            'absent' => $rows->where('status', 'absent')->count(),
            'leave' => $rows->where('status', 'on_leave')->count(),
            'worked' => (int) $rows->sum('worked_minutes'),
            'overtime' => (int) $rows->sum('approved_overtime_minutes'),
            'night' => (int) $rows->sum('night_minutes'),
            'out_of_zone' => $rows->filter->isOutOfZone()->count(),
        ];

        return view('my-attendance.history', compact('rows', 'month', 'summary'));
    }

    protected function rules(): array
    {
        return [
            'type' => ['required', 'in:in,out'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'selfie' => ['nullable', 'string', 'max:6000000'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'captured_at' => ['nullable', 'date'],
            'offline' => ['nullable', 'boolean'],
        ];
    }

    protected function payload(array $result): array
    {
        $a = $result['attendance'];

        return [
            'message' => $result['message'],
            'out_of_zone' => $result['out_of_zone'],
            'site' => $result['site']?->name,
            'distance' => $result['distance'],
            'attendance' => [
                'id' => $a->id,
                'status' => $a->status,
                'status_label' => $a->statusLabel(),
                'check_in' => $a->check_in_at?->format('H:i'),
                'check_out' => $a->check_out_at?->format('H:i'),
                'worked' => hours($a->worked_minutes),
            ],
        ];
    }
}
