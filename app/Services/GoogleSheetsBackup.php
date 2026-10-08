<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copia de seguridad de la asistencia, empleados y nómina en Google Sheets usando una
 * cuenta de servicio (JWT firmado con su clave privada, sin dependencias adicionales).
 * Si no hay credenciales configuradas, guarda la copia como CSV en storage/app/private/backups.
 */
class GoogleSheetsBackup
{
    public const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    /**
     * @return array{target: string, sheets: array<string, int>}
     */
    public function run(int $days = 60): array
    {
        $sheets = $this->datasets($days);
        $spreadsheet = (string) setting('sheets_spreadsheet_id');
        $credentials = (string) setting('sheets_credentials_path');

        if ($spreadsheet === '' || $credentials === '') {
            return ['target' => $this->writeCsv($sheets), 'sheets' => array_map('count', $sheets)];
        }

        $token = $this->accessToken($credentials);

        foreach ($sheets as $title => $rows) {
            $this->writeSheet($token, $spreadsheet, $title, $rows);
        }

        return ['target' => "https://docs.google.com/spreadsheets/d/{$spreadsheet}", 'sheets' => array_map('count', $sheets)];
    }

    /**
     * @return array<string, array<int, array<int, mixed>>>
     */
    public function datasets(int $days): array
    {
        $since = now()->subDays($days)->toDateString();

        $attendance = [['Fecha', 'Código', 'Empleado', 'Estado', 'Entrada', 'Salida', 'Sitio', 'Fuera de zona', 'Min. trabajados', 'Min. extra aprob.', 'Min. nocturnos', 'Origen']];
        Attendance::with(['user', 'site'])->whereDate('date', '>=', $since)->orderBy('date')->chunk(500, function ($chunk) use (&$attendance) {
            foreach ($chunk as $a) {
                $attendance[] = [
                    $a->date->toDateString(), $a->user?->employee_code, $a->user?->name, $a->statusLabel(),
                    $a->check_in_at?->format('Y-m-d H:i'), $a->check_out_at?->format('Y-m-d H:i'), $a->site?->name,
                    $a->isOutOfZone() ? 'Sí' : 'No', $a->worked_minutes, $a->approved_overtime_minutes, $a->night_minutes, $a->source,
                ];
            }
        });

        $employees = [['Código', 'Nombre', 'Correo', 'Rol', 'Departamento', 'Cargo', 'Tipo de pago', 'Estado', 'Ingreso']];
        foreach (User::with(['role', 'department'])->orderBy('name')->get() as $u) {
            $employees[] = [
                $u->employee_code, $u->name, $u->email, $u->role?->name, $u->department?->name, $u->position,
                $u->pay_type, $u->status, $u->hire_date?->toDateString(),
            ];
        }

        $payroll = [['Período', 'Código', 'Empleado', 'Devengado', 'Deducciones', 'Neto']];
        foreach (Payslip::with(['period', 'user'])->latest('id')->limit(2000)->get() as $p) {
            $payroll[] = [$p->period?->name, $p->user?->employee_code, $p->user?->name, $p->gross_pay, $p->total_deductions, $p->net_pay];
        }

        return ['Asistencia' => $attendance, 'Empleados' => $employees, 'Nomina' => $payroll];
    }

    protected function writeCsv(array $sheets): string
    {
        $dir = 'backups/'.now()->format('Y-m-d_His');

        foreach ($sheets as $title => $rows) {
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($handle, $row, escape: '');
            }
            rewind($handle);
            Storage::disk('local')->put("{$dir}/{$title}.csv", stream_get_contents($handle));
            fclose($handle);
        }

        return Storage::disk('local')->path($dir);
    }

    protected function accessToken(string $credentialsPath): string
    {
        $path = str_starts_with($credentialsPath, '/') ? $credentialsPath : storage_path($credentialsPath);

        if (! is_readable($path)) {
            throw new RuntimeException("No se puede leer el archivo de credenciales: {$path}");
        }

        $account = json_decode((string) file_get_contents($path), true);

        if (empty($account['client_email']) || empty($account['private_key'])) {
            throw new RuntimeException('El archivo de credenciales no es una cuenta de servicio válida.');
        }

        $now = time();
        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64Url(json_encode([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        openssl_sign(implode('.', $segments), $signature, $account['private_key'], 'sha256WithRSAEncryption');
        $segments[] = $this->base64Url($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => implode('.', $segments),
        ])->throw();

        return (string) $response->json('access_token');
    }

    protected function writeSheet(string $token, string $spreadsheet, string $title, array $rows): void
    {
        $base = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet}";
        $client = Http::withToken($token);

        $existing = collect($client->get($base, ['fields' => 'sheets.properties.title'])->throw()->json('sheets', []))
            ->pluck('properties.title');

        if (! $existing->contains($title)) {
            $client->post("{$base}:batchUpdate", [
                'requests' => [['addSheet' => ['properties' => ['title' => $title]]]],
            ])->throw();
        }

        $range = rawurlencode("'{$title}'");
        $client->post("{$base}/values/{$range}:clear")->throw();
        $client->put("{$base}/values/{$range}!A1?valueInputOption=RAW", ['values' => $rows])->throw();
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
