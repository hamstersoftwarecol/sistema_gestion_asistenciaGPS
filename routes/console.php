<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tareas programadas (cron): agrega en el servidor
|   * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('hr:alerts')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('attendance:close-day')->dailyAt('00:15');
Schedule::command('backup:google-sheets')->dailyAt('02:00');
