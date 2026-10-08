<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PayrollPeriod;
use App\Models\Site;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', ['reports' => ReportService::REPORTS]);
    }

    public function show(Request $request, string $report): View|Response
    {
        abort_unless(array_key_exists($report, ReportService::REPORTS), 404);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'period_id' => ['nullable', 'integer'],
            'export' => ['nullable', Rule::in(['csv'])],
        ]);

        $filters['from'] ??= now()->startOfMonth()->toDateString();
        $filters['to'] ??= now()->toDateString();

        $result = (new ReportService($request->user()))->run($report, $filters);

        if (($filters['export'] ?? null) === 'csv') {
            return Csv::download("{$report}-{$filters['from']}-{$filters['to']}.csv", $result['columns'], $result['rows']);
        }

        return view('reports.show', [
            'report' => $report,
            'meta' => ReportService::REPORTS[$report],
            'result' => $result,
            'filters' => $filters,
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'sites' => Site::orderBy('name')->pluck('name', 'id'),
            'employees' => User::visibleTo($request->user())->orderBy('name')->pluck('name', 'id'),
            'periods' => PayrollPeriod::orderByDesc('start_date')->pluck('name', 'id'),
        ]);
    }
}
