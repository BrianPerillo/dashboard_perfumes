' Ejecuta un .bat SIN ventana de consola y espera a que termine (devuelve su codigo de salida).
' Las tareas de Windows en modo "Interactive" (alertas_perfumes, descuentos_ropa) abrian una consola negra en el
' escritorio cada vez que corrian; las tareas ahora llaman a este lanzador en vez de al .bat directamente:
'   wscript.exe //B //Nologo scripts\ejecutar_oculto.vbs <ruta-al.bat> [argumentos del .bat...]
' La salida de cada .bat ya va a su log (logs_ropa.txt, storage\logs\alertas_cron.log). Ver ai-context\11-entorno-config-despliegue.md
Option Explicit

Dim sh, fso, bat, args, i, cmd, carpeta
Set sh = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

If WScript.Arguments.Count = 0 Then WScript.Quit 2

bat = WScript.Arguments(0)
If Not fso.FileExists(bat) Then WScript.Quit 3

args = ""
For i = 1 To WScript.Arguments.Count - 1
    args = args & " " & WScript.Arguments(i)
Next

' El .bat hace "cd /d %~dp0.." por su cuenta; se parte de su carpeta por prolijidad
carpeta = fso.GetParentFolderName(bat)
sh.CurrentDirectory = carpeta

' cmd /c ""ruta.bat" args"  (estilo 0 = ventana oculta; True = esperar el fin)
cmd = "cmd /c " & Chr(34) & Chr(34) & bat & Chr(34) & args & Chr(34)
WScript.Quit sh.Run(cmd, 0, True)
