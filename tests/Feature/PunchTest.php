<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedCatalogs();
        // Miércoles 7 de octubre de 2026, día laboral.
        Carbon::setTestNow(Carbon::parse('2026-10-07 06:58', 'America/Bogota'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function punch($user, string $type, float $lat, float $lng, array $extra = [])
    {
        return $this->actingAs($user)->postJson(route('my-attendance.punch'), $extra + [
            'type' => $type,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => 12,
            'selfie' => $this->selfie(),
            'device_id' => 'device-a',
        ]);
    }

    public function test_check_in_and_out_with_selfie_inside_the_nearest_geofence(): void
    {
        $site = $this->site();
        $this->site(['name' => 'Chía', 'latitude' => 4.8616, 'longitude' => -74.0329, 'radius_m' => 500]);
        $user = $this->employee(['shift_id' => $this->dayShift()->id, 'site_id' => $site->id]);

        $this->punch($user, 'in', 4.6965, -74.0320)
            ->assertOk()
            ->assertJson(['out_of_zone' => false, 'site' => 'Obra Usaquén']);

        $row = Attendance::firstOrFail();
        $this->assertSame($site->id, $row->site_id);
        $this->assertSame('present', $row->status);
        $this->assertLessThan(50, $row->check_in_distance_m);
        Storage::disk('local')->assertExists($row->check_in_selfie);

        Carbon::setTestNow(Carbon::parse('2026-10-07 17:30', 'America/Bogota'));
        $this->punch($user, 'out', 4.6964, -74.0321)->assertOk();

        $row->refresh();
        // 06:58–17:30 = 632 min − 60 de descanso = 572 trabajados; jornada 480 → 92 min extra pendientes.
        $this->assertSame(572, $row->worked_minutes);
        $this->assertSame(480, $row->regular_minutes);
        $this->assertSame(92, $row->overtime_minutes);
        $this->assertSame('pending', $row->overtime_status);
        $this->assertSame(0, $row->approved_overtime_minutes);
    }

    public function test_out_of_zone_punch_is_flagged_or_blocked_by_policy(): void
    {
        $this->site();
        $user = $this->employee(['shift_id' => $this->dayShift()->id]);

        // ~1 km al norte del sitio.
        $this->punch($user, 'in', 4.7053, -74.0322)->assertOk()->assertJson(['out_of_zone' => true]);
        $this->assertTrue(Attendance::firstOrFail()->check_in_out_of_zone);

        Setting::write('out_of_zone_action', 'block');
        $other = $this->employee();
        $this->punch($other, 'in', 4.7053, -74.0322)->assertStatus(422);
        $this->assertSame(1, Attendance::count());
    }

    public function test_late_check_in_beyond_grace_is_marked_late(): void
    {
        $this->site();
        $user = $this->employee(['shift_id' => $this->dayShift()->id]);

        Carbon::setTestNow(Carbon::parse('2026-10-07 07:25', 'America/Bogota'));
        $this->punch($user, 'in', 4.6963, -74.0322)->assertOk();

        $row = Attendance::firstOrFail();
        $this->assertSame('late', $row->status);
        $this->assertSame(25, $row->late_minutes);
    }

    public function test_offline_queue_replays_with_the_captured_time(): void
    {
        $this->site();
        $user = $this->employee(['shift_id' => $this->dayShift()->id]);
        Carbon::setTestNow(Carbon::parse('2026-10-07 11:00', 'America/Bogota'));

        $this->actingAs($user)->postJson(route('my-attendance.sync'), [
            'punches' => [
                ['client_id' => 'b', 'type' => 'out', 'latitude' => 4.6963, 'longitude' => -74.0322, 'selfie' => $this->selfie(), 'captured_at' => '2026-10-07T15:00:00Z'],
                ['client_id' => 'a', 'type' => 'in', 'latitude' => 4.6963, 'longitude' => -74.0322, 'selfie' => $this->selfie(), 'captured_at' => '2026-10-07T12:02:00Z'],
            ],
        ])->assertOk()->assertJsonPath('results.0.client_id', 'a')->assertJsonPath('results.0.ok', true)->assertJsonPath('results.1.ok', true);

        $row = Attendance::firstOrFail();
        $this->assertTrue($row->is_offline);
        $this->assertSame('offline', $row->source);
        $this->assertSame('07:02', $row->check_in_at->format('H:i'));
        $this->assertSame('10:00', $row->check_out_at->format('H:i'));
    }

    public function test_stale_offline_punches_are_rejected(): void
    {
        $this->site();
        $user = $this->employee();

        $this->actingAs($user)->postJson(route('my-attendance.sync'), [
            'punches' => [['client_id' => 'x', 'type' => 'in', 'latitude' => 4.6963, 'longitude' => -74.0322, 'selfie' => $this->selfie(), 'captured_at' => now()->subHours(30)->toIso8601String()]],
        ])->assertOk()->assertJsonPath('results.0.ok', false)->assertJsonPath('results.0.discard', true);

        $this->assertSame(0, Attendance::count());
    }

    public function test_device_binding_rejects_a_second_device(): void
    {
        Setting::write('device_binding', true);
        $this->site();
        $user = $this->employee();

        $this->punch($user, 'in', 4.6963, -74.0322)->assertOk();
        $this->assertSame('device-a', $user->refresh()->device_id);

        Carbon::setTestNow(now()->addHours(9));
        $this->punch($user, 'out', 4.6963, -74.0322, ['device_id' => 'device-b'])->assertStatus(422);
    }

    public function test_selfie_is_required_and_double_check_in_is_rejected(): void
    {
        $this->site();
        $user = $this->employee();

        $this->punch($user, 'in', 4.6963, -74.0322, ['selfie' => null])->assertStatus(422);
        $this->punch($user, 'in', 4.6963, -74.0322)->assertOk();
        $this->punch($user, 'in', 4.6963, -74.0322)->assertStatus(422);
    }

    public function test_locked_payroll_period_blocks_punches(): void
    {
        $this->site();
        $user = $this->employee();
        PayrollPeriod::create(['name' => 'Octubre', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'locked']);

        $this->punch($user, 'in', 4.6963, -74.0322)->assertStatus(422);
    }

    public function test_night_shift_check_in_after_midnight_belongs_to_the_previous_day(): void
    {
        $this->site();
        $night = $this->dayShift(['name' => 'Nocturno', 'code' => 'N', 'start_time' => '22:00', 'end_time' => '06:00', 'break_minutes' => 30]);
        $user = $this->employee(['shift_id' => $night->id]);

        Carbon::setTestNow(Carbon::parse('2026-10-08 00:20', 'America/Bogota'));
        $this->punch($user, 'in', 4.6963, -74.0322)->assertOk();

        $this->assertSame('2026-10-07', Attendance::firstOrFail()->date->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-10-08 06:00', 'America/Bogota'));
        $this->punch($user, 'out', 4.6963, -74.0322)->assertOk();

        $row = Attendance::firstOrFail();
        // 140 min tarde supera el umbral de medio día (120 min).
        $this->assertSame(140, $row->late_minutes);
        $this->assertSame('half_day', $row->status);
        $this->assertGreaterThan(300, $row->night_minutes);
    }

    public function test_selfies_are_private(): void
    {
        $this->site();
        $owner = $this->employee();
        $this->punch($owner, 'in', 4.6963, -74.0322)->assertOk();
        $row = Attendance::firstOrFail();

        $this->actingAs($owner)->get(route('attendance.selfie', [$row, 'in']))->assertOk();
        $this->actingAs($this->employee())->get(route('attendance.selfie', [$row, 'in']))->assertForbidden();
        $this->actingAs($this->employee([], 'hr'))->get(route('attendance.selfie', [$row, 'in']))->assertOk();
    }
}
