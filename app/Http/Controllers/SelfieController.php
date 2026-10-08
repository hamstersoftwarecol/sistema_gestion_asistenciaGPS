<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SelfieController extends Controller
{
    /**
     * Las selfies se guardan en el disco privado y solo las ve el propio empleado o su supervisión.
     */
    public function __invoke(Request $request, Attendance $attendance, string $type): Response
    {
        $user = $request->user();

        abort_unless(
            $attendance->user_id === $user->id || ($user->can('attendance.view') && $user->canSee($attendance->user)),
            403,
        );

        $path = $type === 'out' ? $attendance->check_out_selfie : $attendance->check_in_selfie;

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
