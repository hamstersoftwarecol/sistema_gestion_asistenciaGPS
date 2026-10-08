<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\ContractorInvoice;
use App\Services\ContractorInvoiceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractorInvoiceController extends Controller
{
    public function index(): View
    {
        return view('invoices.index', [
            'invoices' => ContractorInvoice::with('contractor')->latest()->paginate(20),
            'contractors' => Contractor::where('active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, ContractorInvoiceService $service): RedirectResponse
    {
        $data = $request->validate([
            'contractor_id' => ['required', 'exists:contractors,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = $service->generate(
            Contractor::findOrFail($data['contractor_id']),
            Carbon::parse($data['period_start']),
            Carbon::parse($data['period_end']),
            $request->user(),
            isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
            $data['notes'] ?? null,
        );

        return redirect()->route('invoices.show', $invoice)->with('status', "Factura {$invoice->number} generada desde la asistencia.");
    }

    public function show(ContractorInvoice $invoice): View
    {
        return view('invoices.show', ['invoice' => $invoice->load('contractor', 'creator')]);
    }

    public function update(Request $request, ContractorInvoice $invoice): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ContractorInvoice::STATUSES))]]);
        $invoice->update($data);

        return back()->with('status', 'Estado de la factura actualizado.');
    }

    public function destroy(ContractorInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === 'draft', 422, 'Solo se eliminan facturas en borrador.');
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'Factura eliminada.');
    }
}
