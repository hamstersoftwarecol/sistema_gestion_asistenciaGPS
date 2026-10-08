<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sitios de proyecto con su geocerca (centro + radio en metros).
 */
class SiteController extends Controller
{
    public function index(): View
    {
        $sites = Site::withCount('employees')->orderBy('name')->get();

        return view('sites.index', compact('sites'));
    }

    public function create(): View
    {
        return view('sites.form', ['site' => new Site(['radius_m' => 150, 'active' => true]), 'others' => Site::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Site::create($this->validated($request));

        return redirect()->route('sites.index')->with('status', 'Sitio creado.');
    }

    public function edit(Site $site): View
    {
        return view('sites.form', ['site' => $site, 'others' => Site::whereKeyNot($site->id)->get()]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $site->update($this->validated($request, $site));

        return redirect()->route('sites.index')->with('status', 'Sitio actualizado.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        if ($site->attendances()->exists()) {
            $site->update(['active' => false]);

            return redirect()->route('sites.index')->with('status', 'El sitio tiene registros de asistencia: se desactivó en lugar de eliminarse.');
        }

        $site->delete();

        return redirect()->route('sites.index')->with('status', 'Sitio eliminado.');
    }

    protected function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('sites')->ignore($site)],
            'client' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'min:10', 'max:20000'],
            'active' => ['boolean'],
        ]) + ['active' => $request->boolean('active')];
    }
}
