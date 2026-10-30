<#
  Registra / actualiza las tareas programadas de Windows del proyecto (idempotente).

    descuentos_master_perfumes : scraper, diario 13:00  -> scripts\descuentos_master.bat
    alertas_perfumes           : alertas, cada 3 horas  -> scripts\alertas_procesar.bat
    descuentos_ropa            : scraper de ropa, diario 15:00 -> scripts\descuentos_ropa.bat

  Uso (PowerShell, como el usuario que ejecuta el scraper):
    powershell -ExecutionPolicy Bypass -File scripts\instalar_tareas_windows.ps1

  - Si la tarea del scraper ya existe, solo se cambia su ACCION (se conservan usuario,
    credenciales y ajustes).
  - Ver ai-context\11-entorno-config-despliegue.md
#>
$ErrorActionPreference = 'Stop'
$scripts = $PSScriptRoot
$usuario = "$env:USERDOMAIN\$env:USERNAME"

function Nueva-Accion($bat) {
    New-ScheduledTaskAction -Execute (Join-Path $scripts $bat) -WorkingDirectory (Split-Path $scripts -Parent)
}

# ── 1. Scraper ───────────────────────────────────────────────────────────────
$nombreScraper = 'descuentos_master_perfumes'
$existente = Get-ScheduledTask -TaskName $nombreScraper -ErrorAction SilentlyContinue
if ($existente) {
    $accionNueva = Nueva-Accion 'descuentos_master.bat'
    try {
        Set-ScheduledTask -TaskName $nombreScraper -Action $accionNueva | Out-Null
        Write-Host "Tarea '$nombreScraper' actualizada -> $scripts\descuentos_master.bat"
    } catch {
        # La tarea esta guardada con la contrasena del usuario: Windows la pide para modificarla
        try {
            $cred = Get-Credential -UserName $usuario -Message "Contrasena de Windows para actualizar '$nombreScraper'"
            Set-ScheduledTask -TaskName $nombreScraper -Action $accionNueva -User $cred.UserName `
                -Password $cred.GetNetworkCredential().Password | Out-Null
            Write-Host "Tarea '$nombreScraper' actualizada -> $scripts\descuentos_master.bat"
        } catch {
            Write-Warning "No se pudo actualizar '$nombreScraper' ($($_.Exception.Message)). Ejecuta este script en una consola normal (te pedira la contrasena)."
        }
    }
} else {
    $trigger  = New-ScheduledTaskTrigger -Daily -At 13:00
    $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -WakeToRun -MultipleInstances IgnoreNew `
                  -ExecutionTimeLimit (New-TimeSpan -Hours 1) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
    Register-ScheduledTask -TaskName $nombreScraper -Action (Nueva-Accion 'descuentos_master.bat') `
        -Trigger $trigger -Settings $settings -User $usuario -RunLevel Limited | Out-Null
    Write-Host "Tarea '$nombreScraper' creada (diaria 13:00)."
}

# ── 2. Alertas cada 3 horas (primera a las 14:00, 30 min despues del scraper) ─
$nombreAlertas = 'alertas_perfumes'
$accion    = Nueva-Accion 'alertas_procesar.bat'
$trigger   = New-ScheduledTaskTrigger -Once -At '14:00' -RepetitionInterval (New-TimeSpan -Hours 3) `
                -RepetitionDuration (New-TimeSpan -Days 3650)
$settings  = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew `
                -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
# S4U: corre con o sin sesion iniciada sin guardar contrasena, pero Windows lo exige como administrador.
# Sin privilegios se usa 'Interactive' (solo corre mientras el usuario tiene la sesion iniciada).
$modo = 'S4U (con o sin sesion)'
try {
    $principal = New-ScheduledTaskPrincipal -UserId $usuario -LogonType S4U -RunLevel Limited
    Register-ScheduledTask -TaskName $nombreAlertas -Action $accion -Trigger $trigger -Settings $settings `
        -Principal $principal -Force | Out-Null
} catch {
    $modo = 'Interactive (solo con la sesion iniciada)'
    $principal = New-ScheduledTaskPrincipal -UserId $usuario -LogonType Interactive -RunLevel Limited
    Register-ScheduledTask -TaskName $nombreAlertas -Action $accion -Trigger $trigger -Settings $settings `
        -Principal $principal -Force | Out-Null
}
Write-Host "Tarea '$nombreAlertas' registrada (cada 3 horas, desde las 14:00) - modo $modo."

# ── 3. Scraper de ropa (Nike...): diario 15:00, lejos del de perfumes (13:00) ─
$nombreRopa = 'descuentos_ropa'
$accionRopa  = Nueva-Accion 'descuentos_ropa.bat'
$triggerRopa = New-ScheduledTaskTrigger -Daily -At '15:00'
$settingsRopa = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew `
                  -ExecutionTimeLimit (New-TimeSpan -Hours 5) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$principalRopa = New-ScheduledTaskPrincipal -UserId $usuario -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $nombreRopa -Action $accionRopa -Trigger $triggerRopa -Settings $settingsRopa `
    -Principal $principalRopa -Force | Out-Null
Write-Host "Tarea '$nombreRopa' registrada (diaria 15:00) - modo Interactive (solo con la sesion iniciada)."

Get-ScheduledTask -TaskName $nombreScraper, $nombreAlertas, $nombreRopa | ForEach-Object {
    $i = Get-ScheduledTaskInfo $_
    '{0,-28} {1,-8} proxima: {2}' -f $_.TaskName, $_.State, $i.NextRunTime
}
