<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('backup:google-sheets {--days=60 : Días de asistencia a exportar}')]
#[Description('Copia asistencia, empleados y nómina a Google Sheets (o a CSV si no hay credenciales)')]
class BackupToGoogleSheets extends Command
{
    public function handle(GoogleSheetsBackup $backup): int
    {
        try {
            $result = $backup->run((int) $this->option('days'));
        } catch (Throwable $e) {
            $this->error('La copia de seguridad falló: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['sheets'] as $sheet => $rows) {
            $this->line("  {$sheet}: {$rows} filas");
        }

        $this->info("Copia guardada en {$result['target']}");

        return self::SUCCESS;
    }
}
