<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Services\AttendanceCalculator;
use App\Services\Geo;
use App\Services\WorkCalendar;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_haversine_distance_between_bogota_and_medellin(): void
    {
        $km = Geo::distance(4.7110, -74.0721, 6.2442, -75.5812) / 1000;

        $this->assertEqualsWithDelta(237.5, $km, 2.0);
        $this->assertSame(0.0, Geo::distance(4.7, -74.0, 4.7, -74.0));
    }

    public function test_nearest_site_and_geofence(): void
    {
        $near = new Site(['name' => 'Cerca', 'latitude' => 4.6963, 'longitude' => -74.0322, 'radius_m' => 100]);
        $far = new Site(['name' => 'Lejos', 'latitude' => 4.8616, 'longitude' => -74.0329, 'radius_m' => 5000]);

        // ~55 m al norte del sitio cercano.
        $result = Geo::nearestSite(4.6968, -74.0322, collect([$far, $near]));

        $this->assertSame('Cerca', $result['site']->name);
        $this->assertTrue($result['inside']);
        $this->assertEqualsWithDelta(56, $result['distance'], 2);

        // ~330 m: más cercano sigue siendo «Cerca», pero fuera de su radio de 100 m.
        $outside = Geo::nearestSite(4.6993, -74.0322, collect([$far, $near]));
        $this->assertSame('Cerca', $outside['site']->name);
        $this->assertFalse($outside['inside']);

        $this->assertNull(Geo::nearestSite(4.6, -74.0, collect()));
    }

    public function test_night_minutes_cross_midnight(): void
    {
        $calculator = new AttendanceCalculator(new WorkCalendar);

        $in = Carbon::parse('2026-10-07 20:00');
        $out = Carbon::parse('2026-10-08 07:00');

        // Franja 21:00–06:00 = 9 h dentro de la jornada.
        $this->assertSame(540, $calculator->nightMinutes($in, $out, '21:00', '06:00'));
        $this->assertSame(0, $calculator->nightMinutes(Carbon::parse('2026-10-07 07:00'), Carbon::parse('2026-10-07 16:00'), '21:00', '06:00'));
        // Una entrada a las 05:00 suma una hora de la franja que empezó el día anterior.
        $this->assertSame(60, $calculator->nightMinutes(Carbon::parse('2026-10-07 05:00'), Carbon::parse('2026-10-07 13:00'), '21:00', '06:00'));
    }
}
