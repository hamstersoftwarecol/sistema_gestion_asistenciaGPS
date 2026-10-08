<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::with('manager')->withCount('employees')->orderBy('name')->get();

        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('departments.form', ['department' => new Department, 'managers' => $this->managers()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Department::create($this->validated($request));

        return redirect()->route('departments.index')->with('status', 'Departamento creado.');
    }

    public function edit(Department $department): View
    {
        return view('departments.form', ['department' => $department, 'managers' => $this->managers()]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request, $department));

        return redirect()->route('departments.index')->with('status', 'Departamento actualizado.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()->route('departments.index')->with('status', 'Departamento eliminado.');
    }

    protected function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('departments')->ignore($department)],
            'manager_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    protected function managers()
    {
        return User::active()->orderBy('name')->pluck('name', 'id');
    }
}
