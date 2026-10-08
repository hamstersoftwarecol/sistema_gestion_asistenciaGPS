<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(): View
    {
        return view('shifts.index', ['shifts' => Shift::orderBy('start_time')->get()]);
    }

    public function create(): View
    {
        return view('shifts.form', ['shift' => new Shift([
            'start_time' => '08:00', 'end_time' => '17:00', 'grace_minutes' => 10, 'break_minutes' => 60,
            'night_start' => '21:00', 'night_end' => '06:00', 'overtime_cap_minutes' => 240, 'overtime_rate' => 1.25,
            'night_rate' => 1.35, 'weekend_rate' => 1.75, 'holiday_rate' => 1.75, 'color' => '#4f46e5', 'active' => true,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Shift::create($this->validated($request));

        return redirect()->route('shifts.index')->with('status', 'Turno creado.');
    }

    public function edit(Shift $shift): View
    {
        return view('shifts.form', compact('shift'));
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $shift->update($this->validated($request));

        return redirect()->route('shifts.index')->with('status', 'Turno actualizado. Los nuevos parámetros aplican a los cálculos siguientes.');
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return redirect()->route('shifts.index')->with('status', 'Turno eliminado.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:10'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'night_start' => ['required', 'date_format:H:i'],
            'night_end' => ['required', 'date_format:H:i'],
            'overtime_cap_minutes' => ['required', 'integer', 'min:0', 'max:960'],
            'overtime_rate' => ['required', 'numeric', 'min:1', 'max:5'],
            'night_rate' => ['required', 'numeric', 'min:1', 'max:5'],
            'weekend_rate' => ['required', 'numeric', 'min:1', 'max:5'],
            'holiday_rate' => ['required', 'numeric', 'min:1', 'max:5'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'active' => ['boolean'],
        ]) + ['active' => $request->boolean('active')];
    }
}
