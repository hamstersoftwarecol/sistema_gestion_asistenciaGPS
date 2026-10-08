<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);
        $holidays = Holiday::whereYear('date', $year)->orderBy('date')->get();

        return view('holidays.index', compact('holidays', 'year'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:120'],
            'paid' => ['boolean'],
        ]);

        Holiday::create($data + ['paid' => $request->boolean('paid')]);

        return back()->with('status', 'Festivo agregado.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $holiday->update($data + ['paid' => $request->boolean('paid')]);

        return back()->with('status', 'Festivo actualizado.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('status', 'Festivo eliminado.');
    }
}
