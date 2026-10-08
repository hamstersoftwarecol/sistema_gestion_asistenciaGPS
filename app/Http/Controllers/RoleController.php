<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::withCount('users')->orderBy('id')->get(),
            'groups' => Role::PERMISSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'copy_from' => ['nullable', 'exists:roles,id'],
        ]);

        $slug = Str::slug($data['name']);
        $request->merge(['slug' => $slug])->validate(['slug' => [Rule::unique('roles', 'slug')]]);

        Role::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'permissions' => $data['copy_from'] ? Role::find($data['copy_from'])->permissions : ['attendance.self'],
        ]);

        return back()->with('status', "Rol «{$data['name']}» creado.");
    }

    /**
     * Guarda la matriz completa rol × permiso.
     */
    public function update(Request $request): RedirectResponse
    {
        $valid = Role::allPermissionKeys();
        $matrix = $request->input('permissions', []);

        foreach (Role::all() as $role) {
            if ($role->slug === 'admin') {
                continue;
            }

            $role->update([
                'permissions' => array_values(array_intersect($valid, (array) ($matrix[$role->id] ?? []))),
            ]);
        }

        return back()->with('status', 'Permisos actualizados.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 422, 'Los roles del sistema no se pueden eliminar.');
        abort_if($role->users()->exists(), 422, 'Reasigna a los usuarios antes de eliminar el rol.');

        $role->delete();

        return back()->with('status', 'Rol eliminado.');
    }
}
