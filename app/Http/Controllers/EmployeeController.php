<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Contractor;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\PayComponent;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = User::visibleTo($request->user())
            ->with(['role', 'department', 'site', 'shift', 'contractor'])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('employee_code', 'like', "%{$term}%")))
            ->when($request->query('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->query('status', 'active'), fn ($q, $status) => $status === 'all' ? $q : $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('employees.index', ['employees' => $employees, 'departments' => Department::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('employees.form', $this->formData(new User([
            'status' => 'active',
            'pay_type' => 'monthly',
            'hire_date' => today(),
            'role_id' => Role::where('slug', 'employee')->value('id'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password']);

        $employee = User::create($data);
        $employee->forceFill(['email_verified_at' => now()])->save();
        $this->syncComponents($employee, $request);

        return redirect()->route('employees.show', $employee)->with('status', 'Empleado creado.');
    }

    public function show(User $employee): View
    {
        $this->authorizeEmployee($employee);
        $employee->load(['role', 'department', 'site', 'shift', 'manager', 'contractor', 'payComponents', 'documents', 'advances']);

        return view('employees.show', [
            'employee' => $employee,
            'recent' => Attendance::with('site')->where('user_id', $employee->id)->latest('date')->limit(10)->get(),
            'balances' => LeaveBalance::with('leaveType')->where('user_id', $employee->id)->where('year', now()->year)->get(),
        ]);
    }

    public function edit(User $employee): View
    {
        $this->authorizeEmployee($employee);

        return view('employees.form', $this->formData($employee->load('payComponents')));
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $this->authorizeEmployee($employee);
        $data = $this->validated($request, $employee);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        // Evita que un administrador se quite a sí mismo el acceso.
        if ($employee->id === $request->user()->id) {
            unset($data['role_id'], $data['status']);
        }

        $employee->update($data);
        $this->syncComponents($employee, $request);

        return redirect()->route('employees.show', $employee)->with('status', 'Empleado actualizado.');
    }

    public function destroy(Request $request, User $employee): RedirectResponse
    {
        $this->authorizeEmployee($employee);
        abort_if($employee->id === $request->user()->id, 422, 'No puedes eliminar tu propia cuenta.');

        if ($employee->attendances()->exists() || $employee->payslips()->exists()) {
            $employee->update(['status' => 'inactive']);

            return redirect()->route('employees.index')->with('status', 'El empleado tiene historial: se desactivó en lugar de eliminarse.');
        }

        $employee->documents->each(fn ($d) => Storage::disk('local')->delete($d->file_path));
        $employee->delete();

        return redirect()->route('employees.index')->with('status', 'Empleado eliminado.');
    }

    public function resetDevice(User $employee): RedirectResponse
    {
        $this->authorizeEmployee($employee);
        $employee->forceFill(['device_id' => null, 'device_bound_at' => null])->save();

        return back()->with('status', 'Vinculación de dispositivo restablecida. El próximo registro vinculará el nuevo dispositivo.');
    }

    protected function formData(User $employee): array
    {
        return [
            'employee' => $employee,
            'roles' => Role::orderBy('id')->pluck('name', 'id'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'sites' => Site::active()->orderBy('name')->pluck('name', 'id'),
            'shifts' => Shift::active()->orderBy('start_time')->get()->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
            'managers' => User::active()->whereKeyNot($employee->id)->orderBy('name')->pluck('name', 'id'),
            'contractors' => Contractor::where('active', true)->orderBy('name')->pluck('name', 'id'),
            'components' => PayComponent::active()->where('applies_to_all', false)->orderBy('type')->orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request, ?User $employee = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($employee)],
            'password' => [$employee ? 'nullable' : 'required', Password::defaults()],
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($employee)],
            'national_id' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:100'],
            'role_id' => ['required', 'exists:roles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'contractor_id' => ['nullable', 'exists:contractors,id'],
            'pay_type' => ['required', Rule::in(array_keys(User::PAY_TYPES))],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'bank_account' => ['nullable', 'string', 'max:60'],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status' => ['required', Rule::in(array_keys(User::STATUSES))],
        ]);

        $data['monthly_salary'] = (float) ($data['monthly_salary'] ?? 0);
        $data['hourly_rate'] = (float) ($data['hourly_rate'] ?? 0);

        return $data;
    }

    protected function syncComponents(User $employee, Request $request): void
    {
        $selected = collect($request->input('components', []))
            ->filter(fn ($c) => ! empty($c['enabled']))
            ->mapWithKeys(fn ($c, $id) => [(int) $id => ['amount' => ($c['amount'] ?? '') === '' ? null : (float) $c['amount']]]);

        $employee->payComponents()->sync($selected->all());
    }
}
