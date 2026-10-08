<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Siembra la empresa de demostración y recorre todas las pantallas con cada rol.
 */
class DemoSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_screen_renders_with_demo_data(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@demo.test')->firstOrFail();
        $employee = User::where('email', 'empleado@demo.test')->firstOrFail();
        $date = now()->subDays(2)->toDateString();

        $adminPages = [
            '/dashboard', '/my-attendance', '/my-attendance/history', '/corrections', '/corrections/create', '/leave-requests',
            '/leave-requests/create', '/my-payslips', '/live-map', '/live-map/data', '/register', "/register/{$employee->id}/{$date}",
            '/shift-calendar', '/approvals/corrections', '/approvals/overtime', '/approvals/leave', '/employees', '/employees/create',
            "/employees/{$employee->id}", "/employees/{$employee->id}/edit", '/departments', '/departments/create', '/sites', '/sites/create',
            '/shifts', '/shifts/create', '/holidays', '/leave-types', '/leave-types/create', '/leave-balances', '/documents', '/payroll',
            '/payroll/1', '/payroll/2', '/payslips/1', '/advances', '/pay-components', '/pay-components/create', '/contractors',
            '/contractors/create', '/invoices', '/invoices/1', '/settlements', '/settlements/create', '/settlements/1', '/reports',
            '/settings', '/roles', '/notifications', '/profile',
        ];

        foreach ($adminPages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }

        foreach (['/dashboard', '/my-attendance', '/my-attendance/history', '/leave-requests', '/my-payslips'] as $page) {
            $this->actingAs($employee)->get($page)->assertOk();
        }

        $this->actingAs($employee)->get('/employees')->assertForbidden();
    }
}
