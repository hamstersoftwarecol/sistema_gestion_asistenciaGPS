<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Contractor;
use App\Models\Department;
use App\Models\FinalSettlement;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $hr;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalogs();
        Carbon::setTestNow(Carbon::parse('2026-10-08 12:00', 'America/Bogota'));
        $this->hr = $this->employee([], 'hr');
        $this->manager = $this->employee([], 'manager');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Fila de asistencia completa calculada como si viniera de la app.
     */
    protected function worked(User $user, string $date, string $in, string $out, array $extra = []): Attendance
    {
        $row = new Attendance(['user_id' => $user->id, 'date' => $date, 'status' => 'present', 'source' => 'app'] + $extra);
        $row->check_in_at = Carbon::parse("{$date} {$in}");
        $row->check_out_at = Carbon::parse("{$date} {$out}");
        $row->setRelation('user', $user);
        app(AttendanceCalculator::class)->recalculate($row)->save();

        return $row;
    }

    public function test_manager_approves_a_missed_check_out_correction(): void
    {
        $shift = $this->dayShift();
        $employee = $this->employee(['shift_id' => $shift->id, 'manager_id' => $this->manager->id]);
        $row = Attendance::create(['user_id' => $employee->id, 'date' => '2026-10-06', 'shift_id' => $shift->id, 'status' => 'missed_punch', 'check_in_at' => '2026-10-06 07:00:00']);

        $this->actingAs($employee)->post(route('corrections.store'), [
            'date' => '2026-10-06', 'type' => 'missed_check_out', 'check_out' => '16:05', 'reason' => 'Sin batería al salir',
        ])->assertRedirect(route('corrections.index'));

        $correction = AttendanceCorrection::firstOrFail();
        $this->assertSame(1, $this->manager->notifications()->count());

        // El empleado no puede aprobar su propia solicitud.
        $this->actingAs($employee)->post(route('corrections.approve', $correction))->assertForbidden();

        $this->actingAs($this->manager)->post(route('corrections.approve', $correction))->assertRedirect();

        $row->refresh();
        $this->assertSame('present', $row->status);
        $this->assertTrue($row->regularized);
        $this->assertSame(485, $row->worked_minutes);
        $this->assertSame('approved', $correction->refresh()->status);
    }

    public function test_managers_only_see_their_team(): void
    {
        $team = $this->employee(['manager_id' => $this->manager->id]);
        $department = Department::create(['name' => 'Planta', 'manager_id' => $this->manager->id]);
        $inDepartment = $this->employee(['department_id' => $department->id]);
        $stranger = $this->employee();

        $visible = User::visibleTo($this->manager)->pluck('id');
        $this->assertTrue($visible->contains($team->id));
        $this->assertTrue($visible->contains($inDepartment->id));
        $this->assertFalse($visible->contains($stranger->id));

        $this->actingAs($this->manager)->get(route('register.edit', [$team, '2026-10-07']))->assertOk();
        $this->actingAs($this->manager)->get(route('register.edit', [$stranger, '2026-10-07']))->assertForbidden();
        $this->actingAs($this->manager)->get(route('payroll.index'))->assertForbidden();
        $this->actingAs($team)->get(route('register.index'))->assertForbidden();
    }

    public function test_bulk_marking_uses_the_shift_schedule_and_keeps_app_punches(): void
    {
        $shift = $this->dayShift();
        $a = $this->employee(['shift_id' => $shift->id]);
        $b = $this->employee(['shift_id' => $shift->id]);
        $this->worked($b, '2026-10-07', '07:00', '16:00');

        $this->actingAs($this->hr)->post(route('register.store'), [
            'date' => '2026-10-07',
            'rows' => [
                $a->id => ['apply' => $a->id, 'status' => 'present'],
                $b->id => ['apply' => $b->id, 'status' => 'absent'],
            ],
        ])->assertRedirect();

        $rowA = Attendance::where('user_id', $a->id)->firstOrFail();
        $this->assertSame('bulk', $rowA->source);
        $this->assertSame('07:00', $rowA->check_in_at->format('H:i'));
        $this->assertSame(480, $rowA->worked_minutes);
        $this->assertSame('present', Attendance::where('user_id', $b->id)->value('status'));
    }

    public function test_leave_approval_marks_attendance_and_cancellation_restores_balance(): void
    {
        $employee = $this->employee(['manager_id' => $this->manager->id]);
        $vacation = LeaveType::where('code', 'VAC')->firstOrFail();

        // Lunes 19 a viernes 23 de octubre: 5 días hábiles (domingo es el día de descanso).
        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $vacation->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-23', 'reason' => 'Viaje',
        ])->assertRedirect(route('leave-requests.index'));

        $leave = LeaveRequest::firstOrFail();
        $this->assertSame(5.0, $leave->days);

        $this->actingAs($this->manager)->post(route('leave-requests.approve', $leave))->assertRedirect();

        $this->assertSame(5, Attendance::where('user_id', $employee->id)->where('status', 'on_leave')->count());
        $balance = LeaveBalance::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame(10.0, $balance->available());

        // No hay saldo para 11 días más.
        $this->actingAs($employee)->post(route('leave-requests.store'), [
            'leave_type_id' => $vacation->id, 'start_date' => '2026-11-03', 'end_date' => '2026-11-17',
        ])->assertSessionHasErrors('leave_type_id');

        $this->actingAs($employee)->post(route('leave-requests.cancel', $leave))->assertRedirect();
        $this->assertSame(15.0, $balance->refresh()->available());
        $this->assertSame(0, Attendance::where('user_id', $employee->id)->count());
    }

    public function test_payroll_generation_lock_and_advance_repayment(): void
    {
        $shift = $this->dayShift();
        $employee = $this->employee(['shift_id' => $shift->id, 'monthly_salary' => 3000000]);
        $hourly = $this->employee(['shift_id' => $shift->id, 'pay_type' => 'hourly', 'hourly_rate' => 10000, 'monthly_salary' => 0]);

        $overtime = $this->worked($employee, '2026-09-01', '07:00', '18:00');
        $overtime->update(['overtime_status' => 'approved', 'approved_overtime_minutes' => $overtime->overtime_minutes]);
        Attendance::create(['user_id' => $employee->id, 'date' => '2026-09-02', 'status' => 'absent', 'day_type' => 'workday']);
        $this->worked($hourly, '2026-09-01', '07:00', '16:00');
        $this->worked($hourly, '2026-09-02', '07:00', '16:00');

        $advance = Advance::create([
            'user_id' => $employee->id, 'type' => 'advance', 'amount' => 200000, 'installments' => 2, 'installment_amount' => 100000,
            'balance' => 200000, 'issued_on' => '2026-08-20', 'deduct_from' => '2026-09-01', 'status' => 'active',
        ]);

        $this->actingAs($this->hr)->post(route('payroll.store'), ['name' => 'Septiembre', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
        $period = PayrollPeriod::firstOrFail();
        $this->actingAs($this->hr)->post(route('payroll.generate', $period))->assertSessionHas('status');

        $slip = $period->payslips()->where('user_id', $employee->id)->with('items')->firstOrFail();
        $daily = 100000.0;
        $hourlyEquivalent = round($daily / 8, 2);
        $this->assertSame(3000000.0, $slip->items->firstWhere('code', 'BASIC')->amount);
        $this->assertSame($daily, $slip->items->firstWhere('code', 'ABSENT')->amount);
        $this->assertSame(round(2 * $hourlyEquivalent * 1.25, 2), $slip->items->firstWhere('code', 'OT')->amount);
        $this->assertSame(120000.0, $slip->items->firstWhere('code', 'SALUD')->amount);
        $this->assertSame(100000.0, $slip->items->firstWhere('code', 'ADVANCE')->amount);
        $this->assertEqualsWithDelta($slip->gross_pay - $slip->total_deductions, $slip->net_pay, 0.01);

        $hourlySlip = $period->payslips()->where('user_id', $hourly->id)->firstOrFail();
        $this->assertSame(16.0, $hourlySlip->regular_hours);
        $this->assertSame(160000.0, $hourlySlip->items()->where('code', 'BASIC')->value('amount'));

        $this->actingAs($this->hr)->post(route('payroll.lock', $period))->assertSessionHas('status');
        $this->assertSame(100000.0, $advance->refresh()->balance);
        $this->assertTrue(PayrollPeriod::dateIsLocked('2026-09-15'));

        // Con el período bloqueado la asistencia ya no se edita.
        $this->actingAs($this->hr)->put(route('register.update', [$employee, '2026-09-02']), ['status' => 'present'])->assertSessionHasErrors('date');

        // Solo el administrador desbloquea; al hacerlo se revierte la cuota.
        $this->actingAs($this->hr)->post(route('payroll.unlock', $period))->assertForbidden();
        $this->actingAs($this->employee([], 'admin'))->post(route('payroll.unlock', $period))->assertSessionHas('status');
        $this->assertSame(200000.0, $advance->refresh()->balance);

        $this->actingAs($this->hr)->get(route('payroll.export', $period))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($employee)->get(route('payslips.show', $slip))->assertForbidden();
    }

    public function test_final_settlement_includes_gratuity_leave_encashment_and_recovers_advances(): void
    {
        $employee = $this->employee(['monthly_salary' => 3000000, 'hire_date' => '2023-10-08']);
        LeaveBalance::create(['user_id' => $employee->id, 'leave_type_id' => LeaveType::where('code', 'VAC')->value('id'), 'year' => 2026, 'allocated' => 15, 'used' => 5]);
        Advance::create([
            'user_id' => $employee->id, 'type' => 'loan', 'amount' => 300000, 'installments' => 3, 'installment_amount' => 100000,
            'balance' => 300000, 'issued_on' => '2026-09-01', 'deduct_from' => '2026-09-01', 'status' => 'active',
        ]);

        $this->actingAs($this->hr)->post(route('settlements.store'), [
            'user_id' => $employee->id, 'termination_date' => '2026-10-08', 'reason' => 'resignation',
        ])->assertRedirect();

        $settlement = FinalSettlement::firstOrFail();
        $this->assertSame(3.0, $settlement->years_of_service);
        $this->assertSame(100000.0 * 15 * 3, $settlement->gratuity_amount);
        $this->assertSame(10.0, $settlement->leave_encashment_days);
        $this->assertSame(1000000.0, $settlement->leave_encashment_amount);
        $this->assertSame(300000.0, $settlement->advances_recovered);

        $this->actingAs($this->hr)->post(route('settlements.approve', $settlement))->assertRedirect();
        $this->assertSame('terminated', $employee->refresh()->status);
        $this->assertSame(0.0, Advance::firstOrFail()->balance);
    }

    public function test_contractor_invoice_is_built_from_attendance(): void
    {
        $contractor = Contractor::create(['name' => 'Temporales', 'rate_type' => 'hourly', 'rate' => 20000, 'overtime_multiplier' => 1.5, 'tax_percent' => 19]);
        $worker = $this->employee(['contractor_id' => $contractor->id, 'shift_id' => $this->dayShift()->id]);
        $row = $this->worked($worker, '2026-09-01', '07:00', '17:00');
        $row->update(['overtime_status' => 'approved', 'approved_overtime_minutes' => 60]);

        $this->actingAs($this->hr)->post(route('invoices.store'), [
            'contractor_id' => $contractor->id, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
        ])->assertRedirect();

        $invoice = $contractor->invoices()->firstOrFail();
        // 8 h × 20.000 + 1 h × 20.000 × 1,5 = 190.000 + IVA 19 %.
        $this->assertSame(190000.0, $invoice->subtotal);
        $this->assertSame(226100.0, $invoice->total);
        $this->assertSame('FC-2026-0001', $invoice->number);
    }

    public function test_close_day_marks_absences_and_missed_punches(): void
    {
        $shift = $this->dayShift();
        $absent = $this->employee(['shift_id' => $shift->id]);
        $open = $this->employee(['shift_id' => $shift->id]);
        Attendance::create(['user_id' => $open->id, 'date' => '2026-10-07', 'shift_id' => $shift->id, 'status' => 'present', 'check_in_at' => '2026-10-07 07:00:00']);

        $this->artisan('attendance:close-day', ['date' => '2026-10-07'])->assertSuccessful();

        $this->assertSame('absent', Attendance::where('user_id', $absent->id)->value('status'));
        $this->assertSame('missed_punch', Attendance::where('user_id', $open->id)->value('status'));
        // Sin turno asignado el día sigue siendo laboral: también cuenta como ausencia.
        $this->assertSame('absent', Attendance::where('user_id', $this->hr->id)->value('status'));

        // Los domingos (descanso por defecto) no generan ausencias.
        $this->artisan('attendance:close-day', ['date' => '2026-10-04'])->assertSuccessful();
        $this->assertSame(0, Attendance::whereDate('date', '2026-10-04')->count());
    }

    public function test_reports_render_and_export_csv(): void
    {
        $employee = $this->employee(['shift_id' => $this->dayShift()->id]);
        $this->worked($employee, '2026-10-05', '07:20', '16:00');

        foreach (['summary', 'muster', 'late', 'overtime', 'out_of_zone', 'leave', 'payroll'] as $report) {
            $this->actingAs($this->hr)->get(route('reports.show', $report))->assertOk();
        }

        $csv = $this->actingAs($this->hr)->get(route('reports.show', ['late', 'from' => '2026-10-01', 'to' => '2026-10-08', 'export' => 'csv']));
        $csv->assertOk()->assertDownload('late-2026-10-01-2026-10-08.csv');
        $this->assertStringContainsString($employee->name, $csv->streamedContent());
    }

    public function test_role_permission_matrix_controls_access(): void
    {
        $admin = $this->employee([], 'admin');
        $employeeRole = Role::where('slug', 'employee')->firstOrFail();
        $employee = $this->employee();

        $this->actingAs($employee)->get(route('live-map.index'))->assertForbidden();

        $this->actingAs($admin)->put(route('roles.update'), [
            'permissions' => [$employeeRole->id => ['attendance.self', 'live_map.view', 'no.existe']],
        ])->assertRedirect();

        $this->assertSame(['attendance.self', 'live_map.view'], $employeeRole->refresh()->permissions);
        $this->actingAs($employee->refresh())->get(route('live-map.index'))->assertOk();
        $this->actingAs($employee)->getJson(route('live-map.data'))->assertOk()->assertJsonStructure(['sites', 'punches', 'stats']);
        $this->actingAs($this->hr)->get(route('roles.index'))->assertForbidden();
    }

    public function test_inactive_users_are_logged_out(): void
    {
        $user = $this->employee(['status' => 'inactive']);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
