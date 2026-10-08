<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Collection;

class Geo
{
    public const EARTH_RADIUS_M = 6371000;

    /**
     * Distancia en metros entre dos coordenadas usando la fórmula de Haversine.
     */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Sitio activo más cercano a la coordenada y si la coordenada cae dentro de su geocerca.
     *
     * @param  Collection<int, Site>|null  $sites
     * @return array{site: Site, distance: int, inside: bool}|null
     */
    public static function nearestSite(float $lat, float $lng, ?Collection $sites = null): ?array
    {
        $sites ??= Site::active()->get();

        $nearest = null;

        foreach ($sites as $site) {
            $distance = self::distance($lat, $lng, $site->latitude, $site->longitude);

            if ($nearest === null || $distance < $nearest['distance']) {
                $nearest = ['site' => $site, 'distance' => $distance];
            }
        }

        if ($nearest === null) {
            return null;
        }

        return [
            'site' => $nearest['site'],
            'distance' => (int) round($nearest['distance']),
            'inside' => $nearest['distance'] <= $nearest['site']->radius_m,
        ];
    }
}
