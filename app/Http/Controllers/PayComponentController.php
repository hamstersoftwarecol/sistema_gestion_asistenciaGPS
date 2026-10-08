<?php

namespace App\Http\Controllers;

use App\Models\PayComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayComponentController extends Controller
{
    public function index(): View
    {
        return view('pay-components.index', ['components' => PayComponent::withCount('employees')->orderBy('type')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('pay-components.form', ['payComponent' => new PayComponent(['type' => 'earning', 'calc_type' => 'fixed', 'active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        PayComponent::create($this->validated($request));

        return redirect()->route('pay-components.index')->with('status', 'Componente de pago creado.');
    }

    public function edit(PayComponent $payComponent): View
    {
        return view('pay-components.form', ['payComponent' => $payComponent]);
    }

    public function update(Request $request, PayComponent $payComponent): RedirectResponse
    {
        $payComponent->update($this->validated($request, $payComponent));

        return redirect()->route('pay-components.index')->with('status', 'Componente de pago actualizado.');
    }

    public function destroy(PayComponent $payComponent): RedirectResponse
    {
        $payComponent->update(['active' => false]);

        return redirect()->route('pay-components.index')->with('status', 'Componente desactivado.');
    }

    protected function validated(Request $request, ?PayComponent $component = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('pay_components')->ignore($component)],
            'type' => ['required', Rule::in(array_keys(PayComponent::TYPES))],
            'calc_type' => ['required', Rule::in(array_keys(PayComponent::CALC_TYPES))],
            'amount' => ['required', 'numeric', 'min:0'],
        ]) + [
            'applies_to_all' => $request->boolean('applies_to_all'),
            'active' => $request->boolean('active'),
        ];
    }
}
