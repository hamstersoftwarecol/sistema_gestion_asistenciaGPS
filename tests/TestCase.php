<?php

namespace Tests;

use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Roles, festivos, tipos de permiso y componentes de pago base.
     */
    protected function seedCatalogs(): void
    {
        $this->seed([RoleSeeder::class, CatalogSeeder::class]);
    }

    protected function site(array $attributes = []): Site
    {
        return Site::create($attributes + [
            'name' => 'Obra Usaquén',
            'code' => 'USQ'.Site::count(),
            'latitude' => 4.6963,
            'longitude' => -74.0322,
            'radius_m' => 200,
            'active' => true,
        ]);
    }

    protected function dayShift(array $attributes = []): Shift
    {
        return Shift::create($attributes + [
            'name' => 'Diurno',
            'code' => 'D',
            'start_time' => '07:00',
            'end_time' => '16:00',
            'grace_minutes' => 10,
            'break_minutes' => 60,
        ]);
    }

    protected function employee(array $attributes = [], string $role = 'employee'): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    /**
     * Pequeña imagen JPEG como data URL, igual a la que envía la cámara del navegador.
     */
    protected function selfie(): string
    {
        $img = imagecreatetruecolor(40, 30);
        imagefill($img, 0, 0, imagecolorallocate($img, 80, 70, 220));
        ob_start();
        imagejpeg($img);
        $jpeg = ob_get_clean();
        imagedestroy($img);

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }
}
