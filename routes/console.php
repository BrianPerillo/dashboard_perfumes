<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// El envío de alertas NO usa el scheduler de Laravel: lo ejecuta la tarea de Windows "alertas_perfumes"
// (cada 3 horas) llamando directo a `php artisan alertas:procesar` vía scripts\alertas_procesar.bat.
// Ver scripts\instalar_tareas_windows.ps1 y ai-context/07-alertas-y-notificaciones.md.
