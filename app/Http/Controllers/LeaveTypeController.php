<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(): View
    {
        return view('leave-types.index', ['types' => LeaveType::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('leave-types.form', ['type' => new LeaveType(['paid' => true, 'active' => true, 'color' => '#0ea5e9'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        LeaveType::create($this->validated($request));

        return redirect()->route('leave-types.index')->with('status', 'Tipo de permiso creado.');
    }

    public function edit(LeaveType $leaveType): View
    {
        return view('leave-types.form', ['type' => $leaveType]);
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($this->validated($request, $leaveType));

        return redirect()->route('leave-types.index')->with('status', 'Tipo de permiso actualizado.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update(['active' => false]);

        return redirect()->route('leave-types.index')->with('status', 'Tipo de permiso desactivado.');
    }

    protected function validated(Request $request, ?LeaveType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:10', Rule::unique('leave_types')->ignore($type)],
            'annual_quota' => ['required', 'numeric', 'min:0', 'max:365'],
            'max_carry_forward' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        foreach (['paid', 'carry_forward', 'encashable', 'requires_attachment', 'active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['max_carry_forward'] = (float) ($data['max_carry_forward'] ?? 0);

        return $data;
    }
}
