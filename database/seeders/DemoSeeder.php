<?php

namespace Database\Seeders;

use App\Models\Advance;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Contractor;
use App\Models\Department;
use App\Models\EmployeeDocument;
use App\Models\FinalSettlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Site;
use App\Models\User;
use App\Notifications\HrAlert;
use App\Services\AttendanceCalculator;
use App\Services\ContractorInvoiceService;
use App\Services\LeaveService;
use App\Services\PayrollService;
use App\Services\SettlementService;
use App\Services\WorkCalendar;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Empresa de demostración con personal de campo en Bogotá y 60 días de asistencia
 * (selfie + GPS) para recorrer todos los módulos: mapa en vivo, aprobaciones, nómina, etc.
 */
class DemoSeeder extends Seeder
{
    protected int $seed = 20261008;

    public function run(): void
    {
        mt_srand($this->seed);

        $roles = Role::pluck('id', 'slug');

        // Sitios con geocerca.
        $sites = collect([
            ['name' => 'Oficina Central Chapinero', 'code' => 'OC', 'client' => 'Sede administrativa', 'address' => 'Cra. 7 #72-41, Bogotá', 'latitude' => 4.6555, 'longitude' => -74.0566, 'radius_m' => 120],
            ['name' => 'Obra Torre Usaquén', 'code' => 'USQ', 'client' => 'Constructora Andina', 'address' => 'Cl. 119 #6-20, Bogotá', 'latitude' => 4.6963, 'longitude' => -74.0322, 'radius_m' => 200],
            ['name' => 'Planta Fontibón', 'code' => 'FTB', 'client' => 'Logística Sabana', 'address' => 'Zona Franca, Fontibón', 'latitude' => 4.6731, 'longitude' => -74.1469, 'radius_m' => 350],
            ['name' => 'Proyecto Vial Chía', 'code' => 'CHI', 'client' => 'Concesión Norte', 'address' => 'Variante Chía–Cajicá', 'latitude' => 4.8616, 'longitude' => -74.0329, 'radius_m' => 500],
        ])->map(fn ($s) => Site::updateOrCreate(['code' => $s['code']], $s + ['active' => true]))->keyBy('code');

        // Turnos.
        $shifts = collect([
            ['name' => 'Oficina', 'code' => 'OF', 'start_time' => '08:00', 'end_time' => '17:00', 'grace_minutes' => 10, 'break_minutes' => 60, 'color' => '#4f46e5'],
            ['name' => 'Diurno campo', 'code' => 'D', 'start_time' => '07:00', 'end_time' => '16:00', 'grace_minutes' => 10, 'break_minutes' => 60, 'color' => '#059669'],
            ['name' => 'Tarde', 'code' => 'T', 'start_time' => '14:00', 'end_time' => '22:00', 'grace_minutes' => 10, 'break_minutes' => 30, 'color' => '#d97706'],
            ['name' => 'Nocturno', 'code' => 'N', 'start_time' => '22:00', 'end_time' => '06:00', 'grace_minutes' => 15, 'break_minutes' => 30, 'color' => '#1e3a8a'],
        ])->map(fn ($s) => Shift::updateOrCreate(['code' => $s['code']], $s + ['night_start' => '21:00', 'night_end' => '06:00', 'overtime_cap_minutes' => 240, 'overtime_rate' => 1.25, 'night_rate' => 1.35, 'weekend_rate' => 1.75, 'holiday_rate' => 1.75, 'active' => true]))->keyBy('code');

        $contractor = Contractor::updateOrCreate(['tax_id' => '901.456.789-1'], [
            'name' => 'Servicios Temporales Andinos S.A.S.', 'contact_name' => 'Marta Ruiz', 'email' => 'facturacion@andinos.test',
            'phone' => '601 555 0101', 'address' => 'Cl. 26 #68-35, Bogotá', 'rate_type' => 'hourly', 'rate' => 18000,
            'overtime_multiplier' => 1.25, 'tax_percent' => 19, 'active' => true,
        ]);

        $password = Hash::make('password');
        $hire = now()->subYears(2)->startOfMonth();

        $make = function (array $data) use ($password, $hire) {
            return User::updateOrCreate(['email' => $data['email']], $data + [
                'password' => $password,
                'email_verified_at' => now(),
                'status' => 'active',
                'pay_type' => 'monthly',
                'hire_date' => $hire,
            ]);
        };

        // Usuarios de demostración (contraseña: password).
        $admin = $make(['name' => 'Ana Administradora', 'email' => 'admin@demo.test', 'role_id' => $roles['admin'], 'employee_code' => 'E-0001', 'position' => 'Gerente general', 'monthly_salary' => 9000000, 'site_id' => $sites['OC']->id, 'shift_id' => $shifts['OF']->id, 'hire_date' => now()->subYears(6)]);
        $hr = $make(['name' => 'Laura Gómez', 'email' => 'rrhh@demo.test', 'role_id' => $roles['hr'], 'employee_code' => 'E-0002', 'position' => 'Jefe de RR. HH.', 'monthly_salary' => 5500000, 'site_id' => $sites['OC']->id, 'shift_id' => $shifts['OF']->id, 'manager_id' => $admin->id, 'hire_date' => now()->subYears(4)]);
        $manager = $make(['name' => 'Carlos Rodríguez', 'email' => 'gerente@demo.test', 'role_id' => $roles['manager'], 'employee_code' => 'E-0003', 'position' => 'Gerente de operaciones', 'monthly_salary' => 6500000, 'site_id' => $sites['USQ']->id, 'shift_id' => $shifts['D']->id, 'manager_id' => $admin->id, 'hire_date' => now()->subYears(3)]);
        $supervisor = $make(['name' => 'Diana Torres', 'email' => 'supervisora@demo.test', 'role_id' => $roles['manager'], 'employee_code' => 'E-0004', 'position' => 'Supervisora de planta', 'monthly_salary' => 4200000, 'site_id' => $sites['FTB']->id, 'shift_id' => $shifts['D']->id, 'manager_id' => $manager->id]);

        $departments = collect([
            ['name' => 'Administración', 'code' => 'ADM', 'manager_id' => $admin->id],
            ['name' => 'Recursos Humanos', 'code' => 'RRHH', 'manager_id' => $hr->id],
            ['name' => 'Operaciones de campo', 'code' => 'OPS', 'manager_id' => $manager->id],
            ['name' => 'Planta y mantenimiento', 'code' => 'MNT', 'manager_id' => $supervisor->id],
        ])->map(fn ($d) => Department::updateOrCreate(['code' => $d['code']], $d))->keyBy('code');

        $admin->update(['department_id' => $departments['ADM']->id]);
        $hr->update(['department_id' => $departments['RRHH']->id]);
        $manager->update(['department_id' => $departments['OPS']->id]);
        $supervisor->update(['department_id' => $departments['MNT']->id]);

        $field = collect([
            ['Juan Pérez', 'empleado@demo.test', 'OPS', 'USQ', 'D', 'Técnico de obra', 1750000],
            ['María Fernanda López', 'maria.lopez@demo.test', 'OPS', 'USQ', 'D', 'Auxiliar SST', 1900000],
            ['Andrés Castillo', 'andres.castillo@demo.test', 'OPS', 'CHI', 'D', 'Topógrafo', 3200000],
            ['Sofía Ramírez', 'sofia.ramirez@demo.test', 'OPS', 'CHI', 'D', 'Inspectora vial', 2800000],
            ['Pedro Salazar', 'pedro.salazar@demo.test', 'OPS', 'USQ', 'T', 'Operador de grúa', 2600000],
            ['Luis Herrera', 'luis.herrera@demo.test', 'MNT', 'FTB', 'N', 'Vigilante de planta', 1750000],
            ['Camila Vargas', 'camila.vargas@demo.test', 'MNT', 'FTB', 'D', 'Técnica de mantenimiento', 2400000],
            ['Jorge Moreno', 'jorge.moreno@demo.test', 'MNT', 'FTB', 'T', 'Electricista', 2700000],
        ])->map(function ($e, $i) use ($make, $roles, $departments, $sites, $shifts, $manager, $supervisor) {
            [$name, $email, $dept, $site, $shift, $position, $salary] = $e;

            return $make([
                'name' => $name, 'email' => $email, 'role_id' => $roles['employee'], 'employee_code' => sprintf('E-%04d', 10 + $i),
                'position' => $position, 'department_id' => $departments[$dept]->id, 'site_id' => $sites[$site]->id,
                'shift_id' => $shifts[$shift]->id, 'manager_id' => $dept === 'OPS' ? $manager->id : $supervisor->id,
                'monthly_salary' => $salary, 'national_id' => (string) (1010000000 + $i * 7919), 'phone' => '300 555 '.str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT),
                'bank_account' => 'Bancolombia Ahorros 2000'.(1000 + $i),
                'hire_date' => $i === 6 ? now()->subMonths(5)->startOfMonth() : now()->subYears(1 + $i % 3)->subMonths($i),
            ]);
        });

        $hourly = collect([
            ['Ricardo Peña', 'ricardo.pena@demo.test', 'USQ', 'Ayudante de obra'],
            ['Valentina Cruz', 'valentina.cruz@demo.test', 'CHI', 'Banderera vial'],
        ])->map(fn ($e, $i) => $make([
            'name' => $e[0], 'email' => $e[1], 'role_id' => $roles['employee'], 'employee_code' => sprintf('H-%04d', 1 + $i),
            'position' => $e[3], 'department_id' => $departments['OPS']->id, 'site_id' => $sites[$e[2]]->id, 'shift_id' => $shifts['D']->id,
            'manager_id' => $manager->id, 'pay_type' => 'hourly', 'hourly_rate' => 11500, 'monthly_salary' => 0, 'hire_date' => now()->subMonths(8),
        ]));

        $contractors = collect([
            ['Óscar Jiménez', 'oscar.jimenez@demo.test', 'FTB'],
            ['Natalia Rojas', 'natalia.rojas@demo.test', 'CHI'],
        ])->map(fn ($e, $i) => $make([
            'name' => $e[0], 'email' => $e[1], 'role_id' => $roles['employee'], 'employee_code' => sprintf('C-%04d', 1 + $i),
            'position' => 'Operario tercerizado', 'department_id' => $departments[$e[2] === 'FTB' ? 'MNT' : 'OPS']->id, 'site_id' => $sites[$e[2]]->id,
            'shift_id' => $shifts['D']->id, 'manager_id' => $e[2] === 'FTB' ? $supervisor->id : $manager->id, 'contractor_id' => $contractor->id,
            'monthly_salary' => 0, 'hire_date' => now()->subMonths(6),
        ]));

        // Componentes individuales.
        $transport = PayComponent::where('code', 'TRANSP')->first();
        $fieldBonus = PayComponent::where('code', 'CAMPO')->first();
        $fund = PayComponent::where('code', 'FONDO')->first();
        $field->each(function (User $u) use ($transport, $fieldBonus) {
            $sync = [$fieldBonus->id => ['amount' => null]];
            if ($u->monthly_salary <= 3500000) {
                $sync[$transport->id] = ['amount' => null];
            }
            $u->payComponents()->sync($sync);
        });
        $hourly->each(fn (User $u) => $u->payComponents()->sync([$transport->id => ['amount' => null]]));
        $manager->payComponents()->sync([$fund->id => ['amount' => 80000]]);

        // Calendario: el vigilante rota nocturno/descanso; Chía trabaja sábados.
        $calendar = app(WorkCalendar::class);
        $from = today()->subDays(60);
        $to = today()->addDays(21);
        $luis = $field[5];
        $chia = $field->filter(fn ($u) => $u->site_id === $sites['CHI']->id)->merge($hourly->where('site_id', $sites['CHI']->id));

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $week = intdiv((int) $day->diffInDays($from), 7);
            ShiftAssignment::updateOrCreate(['user_id' => $luis->id, 'date' => $day->toDateString()], [
                'shift_id' => in_array($day->dayOfWeek, [0, 1], true) ? null : $shifts['N']->id,
                'site_id' => $sites['FTB']->id,
                'is_off' => in_array($day->dayOfWeek, [0, 1], true),
                'assigned_by' => $hr->id,
            ]);

            foreach ($chia as $u) {
                ShiftAssignment::updateOrCreate(['user_id' => $u->id, 'date' => $day->toDateString()], [
                    'shift_id' => $day->dayOfWeek === 0 ? null : $shifts['D']->id,
                    'site_id' => $sites['CHI']->id,
                    'is_off' => $day->dayOfWeek === 0,
                    'assigned_by' => $manager->id,
                ]);
            }

            // Pedro rota entre tarde y diurno cada semana, en Usaquén o Fontibón.
            if ($day->dayOfWeek !== 0) {
                ShiftAssignment::updateOrCreate(['user_id' => $field[4]->id, 'date' => $day->toDateString()], [
                    'shift_id' => $week % 2 ? $shifts['D']->id : $shifts['T']->id,
                    'site_id' => $week % 2 ? $sites['FTB']->id : $sites['USQ']->id,
                    'is_off' => false,
                    'assigned_by' => $manager->id,
                ]);
            }
        }

        // Selfies de demostración (una imagen por empleado).
        $everyone = collect([$admin, $hr, $manager, $supervisor])->merge($field)->merge($hourly)->merge($contractors);
        $selfies = $everyone->mapWithKeys(fn (User $u) => [$u->id => $this->demoSelfie($u)]);

        // Asistencia de los últimos 60 días.
        $calculator = app(AttendanceCalculator::class);
        $calendar->preloadHolidays($from, today());

        foreach ($everyone as $user) {
            foreach (CarbonPeriod::create($from, today()) as $day) {
                $schedule = $calendar->scheduleFor($user, $day);
                $isToday = $day->isToday();

                if (! $schedule['scheduled'] || ! $schedule['shift']) {
                    // Algunos trabajan en su día de descanso o en festivo: genera recargos.
                    if (mt_rand(1, 100) > 10 || $isToday || ! $user->shift) {
                        continue;
                    }
                    $schedule['shift'] = $user->shift;
                }

                $roll = mt_rand(1, 100);

                if ($roll <= 4 && ! $isToday) {
                    Attendance::updateOrCreate(['user_id' => $user->id, 'date' => $day->toDateString()], [
                        'shift_id' => $schedule['shift']->id, 'site_id' => $schedule['site']?->id, 'day_type' => 'workday',
                        'status' => 'absent', 'source' => 'system',
                    ]);

                    continue;
                }

                [$start, $end] = $schedule['shift']->windowFor($day);

                if ($isToday && now()->lessThan($start->copy()->subMinutes(15))) {
                    continue;
                }

                $late = $roll <= 16;
                $in = $start->copy()->addMinutes($late ? mt_rand(15, 50) : mt_rand(-12, 7))->addSeconds(mt_rand(0, 59));
                $out = $end->copy()->addMinutes(mt_rand(1, 100) <= 25 ? mt_rand(30, 150) : mt_rand(-5, 12))->addSeconds(mt_rand(0, 59));
                $missedOut = (mt_rand(1, 100) <= 3 && ! $isToday) || $out->greaterThan(now());
                $outOfZone = mt_rand(1, 100) <= 6;
                $site = $schedule['site'] ?? $user->site;
                [$inLat, $inLng, $inDist] = $this->near($site, $outOfZone);
                [$outLat, $outLng, $outDist] = $this->near($site, false);
                $offline = mt_rand(1, 100) <= 4;

                $row = Attendance::firstOrNew(['user_id' => $user->id, 'date' => $day->toDateString()]);
                $row->setRelation('user', $user);
                $row->fill([
                    'shift_id' => $schedule['shift']->id,
                    'site_id' => $site?->id,
                    'status' => 'present',
                    'check_in_at' => $in,
                    'check_in_lat' => $inLat, 'check_in_lng' => $inLng, 'check_in_accuracy' => mt_rand(5, 35),
                    'check_in_distance_m' => $inDist, 'check_in_out_of_zone' => $outOfZone || ($site && $inDist > $site->radius_m),
                    'check_in_selfie' => $selfies[$user->id],
                    'source' => $offline ? 'offline' : 'app',
                    'is_offline' => $offline,
                    'device_id' => 'demo-device-'.$user->id,
                ]);

                if (! $missedOut) {
                    $row->fill([
                        'check_out_at' => $out, 'check_out_site_id' => $site?->id,
                        'check_out_lat' => $outLat, 'check_out_lng' => $outLng, 'check_out_accuracy' => mt_rand(5, 35),
                        'check_out_distance_m' => $outDist, 'check_out_out_of_zone' => false, 'check_out_selfie' => $selfies[$user->id],
                    ]);
                } elseif (! $isToday && $end->lessThan(now())) {
                    $row->status = 'missed_punch';
                }

                $calculator->recalculate($row);

                // Las horas extra de más de una semana ya fueron revisadas.
                if ($row->overtime_minutes > 0 && $day->lessThan(today()->subDays(7))) {
                    $approve = mt_rand(1, 100) <= 85;
                    $row->overtime_status = $approve ? 'approved' : 'rejected';
                    $row->approved_overtime_minutes = $approve ? $row->overtime_minutes : 0;
                    $row->overtime_reviewed_by = $user->manager_id ?? $hr->id;
                }

                $row->save();
            }
        }

        // Permisos: saldos del año, vacaciones aprobadas, pendientes y rechazados.
        $leave = app(LeaveService::class);
        $leave->allocateYear(today()->year);
        $vac = LeaveType::where('code', 'VAC')->first();
        $cal = LeaveType::where('code', 'CAL')->first();

        $approved = LeaveRequest::create([
            'user_id' => $field[1]->id, 'leave_type_id' => $vac->id,
            'start_date' => today()->subDays(24), 'end_date' => today()->subDays(20),
            'days' => $leave->countDays(today()->subDays(24), today()->subDays(20)), 'reason' => 'Vacaciones familiares', 'status' => 'pending',
        ]);
        Attendance::where('user_id', $field[1]->id)->whereBetween('date', [today()->subDays(24)->toDateString(), today()->subDays(20)->toDateString()])->delete();
        $leave->approve($approved, $manager, 'Disfruta tus vacaciones');

        $leave->request($field[0], $vac, today()->addDays(12), today()->addDays(16), 'Viaje a Cartagena');
        $leave->request($field[6], $cal, today()->addDays(2), today()->addDays(2), 'Cita médica de un familiar');
        $rejected = $leave->request($field[2], $vac, today()->addDays(5), today()->addDays(9), 'Descanso');
        $leave->reject($rejected, $manager, 'Coincide con la entrega del proyecto vial');

        // Correcciones de asistencia.
        $missed = Attendance::where('status', 'missed_punch')->whereIn('user_id', $field->pluck('id'))->latest('date')->take(2)->get();
        foreach ($missed as $row) {
            AttendanceCorrection::create([
                'user_id' => $row->user_id, 'attendance_id' => $row->id, 'date' => $row->date, 'type' => 'missed_check_out',
                'requested_check_out' => $row->shift ? $row->shift->windowFor($row->date)[1]->addMinutes(5) : $row->check_in_at->copy()->addHours(9),
                'reason' => 'Se agotó la batería del celular antes de marcar la salida.',
            ]);
        }
        AttendanceCorrection::create([
            'user_id' => $field[3]->id, 'date' => today()->subDays(3), 'type' => 'regularization',
            'requested_check_in' => today()->subDays(3)->setTime(7, 0), 'requested_check_out' => today()->subDays(3)->setTime(16, 10),
            'reason' => 'Trabajo en tramo sin cobertura de datos ni GPS.',
            'status' => 'rejected', 'reviewed_by' => $manager->id, 'reviewed_at' => now()->subDays(2), 'review_note' => 'Ya existe registro en la app ese día.',
        ]);

        // Anticipos y préstamos.
        Advance::create(['user_id' => $field[0]->id, 'type' => 'advance', 'amount' => 300000, 'installments' => 2, 'installment_amount' => 150000, 'balance' => 300000, 'issued_on' => today()->subDays(50), 'deduct_from' => today()->subDays(45), 'status' => 'active', 'reason' => 'Gastos escolares', 'approved_by' => $hr->id]);
        Advance::create(['user_id' => $field[5]->id, 'type' => 'loan', 'amount' => 1200000, 'installments' => 6, 'installment_amount' => 200000, 'balance' => 1200000, 'issued_on' => today()->subDays(40), 'deduct_from' => today()->subDays(35), 'status' => 'active', 'reason' => 'Préstamo de libre inversión', 'approved_by' => $hr->id]);

        // Documentos con vencimiento.
        $docs = [
            [$field[0], 'Curso trabajo seguro en alturas', 'certificate', today()->addDays(18)],
            [$field[4], 'Licencia de operador de grúa', 'license', today()->addDays(9)],
            [$field[6], 'Examen médico ocupacional', 'medical', today()->addMonths(10)],
            [$field[1], 'Contrato laboral', 'contract', null],
        ];
        foreach ($docs as [$user, $title, $category, $expires]) {
            $path = "documents/{$user->id}/".str($title)->slug().'.txt';
            Storage::disk('local')->put($path, "Documento de demostración: {$title}\nEmpleado: {$user->name}\n");
            EmployeeDocument::create(['user_id' => $user->id, 'title' => $title, 'category' => $category, 'file_path' => $path, 'original_name' => str($title)->slug().'.txt', 'expires_on' => $expires, 'uploaded_by' => $hr->id]);
        }

        // Nómina: mes anterior generado y bloqueado; mes actual en borrador generado.
        $payroll = app(PayrollService::class);
        $previous = today()->subMonthNoOverflow()->startOfMonth();
        $closed = PayrollPeriod::create(['name' => 'Nómina '.ucfirst($previous->locale('es')->isoFormat('MMMM YYYY')), 'start_date' => $previous, 'end_date' => $previous->copy()->endOfMonth(), 'status' => 'draft']);
        $payroll->generate($closed);
        $payroll->lock($closed, $hr);

        $current = today()->startOfMonth();
        $open = PayrollPeriod::create(['name' => 'Nómina '.ucfirst($current->locale('es')->isoFormat('MMMM YYYY')), 'start_date' => $current, 'end_date' => $current->copy()->endOfMonth(), 'status' => 'draft']);
        $payroll->generate($open);

        // Factura del contratista del mes anterior.
        app(ContractorInvoiceService::class)->generate($contractor, $previous, $previous->copy()->endOfMonth(), $hr, null, 'Personal tercerizado en Planta Fontibón y Proyecto Vial Chía.');

        // Liquidación en borrador (renuncia a fin de mes).
        $resigning = $field[7];
        $terminationDate = today()->endOfMonth();
        $computed = app(SettlementService::class)->compute($resigning, Carbon::parse($terminationDate->toDateString()));
        FinalSettlement::create(collect($computed)->except('pending_from')->all() + [
            'user_id' => $resigning->id, 'termination_date' => $terminationDate, 'reason' => 'resignation',
            'notes' => 'Renuncia presentada con 30 días de preaviso.', 'status' => 'draft', 'created_by' => $hr->id,
        ]);

        // Notificaciones de ejemplo.
        $manager->notify(new HrAlert('Bienvenido a GeoAsistencia', 'Revisa el mapa en vivo y las aprobaciones pendientes de tu equipo.', route('live-map.index', absolute: false)));
        $hr->notify(new HrAlert('Documentos por vencer', 'Hay licencias y certificados que vencen en los próximos 30 días.', route('documents.index', ['expiring' => 1], absolute: false), 'warning'));
    }

    /**
     * Coordenada cercana al sitio (dentro de la geocerca) o desplazada fuera de ella.
     *
     * @return array{0: float, 1: float, 2: int}
     */
    protected function near(?Site $site, bool $outside): array
    {
        if (! $site) {
            return [4.6097, -74.0817, 0];
        }

        $distance = $outside ? mt_rand($site->radius_m + 150, $site->radius_m + 900) : mt_rand(3, (int) ($site->radius_m * 0.6));
        $angle = deg2rad(mt_rand(0, 359));
        $dLat = ($distance * cos($angle)) / 111320;
        $dLng = ($distance * sin($angle)) / (111320 * cos(deg2rad($site->latitude)));

        return [round($site->latitude + $dLat, 7), round($site->longitude + $dLng, 7), $distance];
    }

    /**
     * Imagen de demostración para las selfies (círculo de color con iniciales).
     */
    protected function demoSelfie(User $user): string
    {
        $path = "selfies/demo/{$user->id}.jpg";

        if (! function_exists('imagecreatetruecolor')) {
            return $path;
        }

        $img = imagecreatetruecolor(320, 320);
        $hue = crc32($user->email) % 360;
        [$r, $g, $b] = $this->hsl($hue, 0.45, 0.55);
        imagefill($img, 0, 0, imagecolorallocate($img, 226, 232, 240));
        imagefilledellipse($img, 160, 130, 150, 170, imagecolorallocate($img, $r, $g, $b));
        imagefilledellipse($img, 160, 330, 280, 220, imagecolorallocate($img, (int) ($r * 0.7), (int) ($g * 0.7), (int) ($b * 0.7)));
        $white = imagecolorallocate($img, 255, 255, 255);
        $initials = $user->initials();
        imagestring($img, 5, 160 - strlen($initials) * 5, 122, $initials, $white);

        ob_start();
        imagejpeg($img, null, 80);
        Storage::disk('local')->put($path, ob_get_clean());
        imagedestroy($img);

        return $path;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected function hsl(int $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv($h, 60)) {
            0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x], 3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x],
        };

        return [(int) (($r + $m) * 255), (int) (($g + $m) * 255), (int) (($b + $m) * 255)];
    }
}
