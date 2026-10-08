<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveMapController extends Controller
{
    public function index(): View
    {
        return view('live-map.index');
    }

    /**
     * Marcaciones de hoy (y turnos nocturnos abiertos desde ayer) para el mapa en vivo.
     */
    public function data(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $ids = User::visibleTo($viewer)->pluck('id');

        $rows = Attendance::with(['user.department', 'site', 'checkOutSite'])
            ->whereIn('user_id', $ids)
            ->whereNotNull('check_in_at')
            ->where(function ($q) {
                $q->whereDate('date', today()->toDateString())
                    ->orWhere(fn ($w) => $w->whereDate('date', today()->subDay()->toDateString())->whereNull('check_out_at'));
            })
            ->get();

        $canEdit = $viewer->can('attendance.mark');

        $punches = $rows->map(function (Attendance $a) use ($canEdit) {
            $out = $a->check_out_at !== null;

            return [
                'id' => $a->id,
                'name' => $a->user->name,
                'code' => $a->user->employee_code,
                'initials' => $a->user->initials(),
                'department' => $a->user->department?->name,
                'status' => $a->status,
                'status_label' => $a->statusLabel(),
                'on_site' => ! $out,
                'check_in' => $a->check_in_at->format('H:i'),
                'check_out' => $a->check_out_at?->format('H:i'),
                'lat' => $out ? $a->check_out_lat : $a->check_in_lat,
                'lng' => $out ? $a->check_out_lng : $a->check_in_lng,
                'in_lat' => $a->check_in_lat,
                'in_lng' => $a->check_in_lng,
                'out_of_zone' => $a->isOutOfZone(),
                'site' => ($out ? $a->checkOutSite : $a->site)?->name,
                'distance' => $out ? $a->check_out_distance_m : $a->check_in_distance_m,
                'offline' => $a->is_offline,
                'selfie' => $a->check_in_selfie ? route('attendance.selfie', [$a, 'in']) : null,
                'url' => $canEdit ? route('register.edit', [$a->user_id, $a->date->toDateString()]) : null,
            ];
        })->filter(fn ($p) => $p['lat'] !== null)->values();

        return response()->json([
            'generated_at' => now()->format('H:i:s'),
            'sites' => Site::active()->get(['id', 'name', 'latitude', 'longitude', 'radius_m']),
            'punches' => $punches,
            'stats' => [
                'on_site' => $punches->where('on_site', true)->count(),
                'checked_out' => $punches->where('on_site', false)->count(),
                'out_of_zone' => $punches->where('out_of_zone', true)->count(),
                'late' => $punches->where('status', 'late')->count(),
            ],
        ]);
    }
}
