<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractorController extends Controller
{
    public function index(): View
    {
        return view('contractors.index', ['contractors' => Contractor::withCount('workers')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('contractors.form', ['contractor' => new Contractor(['rate_type' => 'hourly', 'tax_percent' => 19, 'overtime_multiplier' => 1.25, 'active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Contractor::create($this->validated($request));

        return redirect()->route('contractors.index')->with('status', 'Contratista creado.');
    }

    public function edit(Contractor $contractor): View
    {
        return view('contractors.form', ['contractor' => $contractor->load('workers')]);
    }

    public function update(Request $request, Contractor $contractor): RedirectResponse
    {
        $contractor->update($this->validated($request));

        return redirect()->route('contractors.index')->with('status', 'Contratista actualizado.');
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        $contractor->update(['active' => false]);

        return redirect()->route('contractors.index')->with('status', 'Contratista desactivado.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'rate_type' => ['required', Rule::in(array_keys(Contractor::RATE_TYPES))],
            'rate' => ['required', 'numeric', 'min:0'],
            'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]) + ['active' => $request->boolean('active')];
    }
}
