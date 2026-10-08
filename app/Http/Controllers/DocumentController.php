<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $team = User::visibleTo($request->user())->pluck('id');

        $documents = EmployeeDocument::with(['user', 'uploader'])
            ->whereIn('user_id', $team)
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->boolean('expiring'), fn ($q) => $q->whereNotNull('expires_on')
                ->whereDate('expires_on', '<=', now()->addDays((int) setting('document_expiry_alert_days'))->toDateString()))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'employees' => User::visibleTo($request->user())->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(EmployeeDocument::CATEGORIES))],
            'expires_on' => ['nullable', 'date'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $employee = User::findOrFail($data['user_id']);
        $this->authorizeEmployee($employee);

        $file = $request->file('file');

        EmployeeDocument::create([
            'user_id' => $employee->id,
            'title' => $data['title'],
            'category' => $data['category'],
            'expires_on' => $data['expires_on'] ?? null,
            'file_path' => $file->store("documents/{$employee->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Documento cargado.');
    }

    public function download(EmployeeDocument $document): StreamedResponse
    {
        $this->authorizeEmployee($document->user);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroy(EmployeeDocument $document): RedirectResponse
    {
        $this->authorizeEmployee($document->user);
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('status', 'Documento eliminado.');
    }
}
