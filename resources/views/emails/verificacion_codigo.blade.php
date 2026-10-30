<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Código de verificación</title>
<style>
    body { margin: 0; padding: 0; background: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .wrapper { max-width: 480px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
    .header { background: linear-gradient(135deg, #8a1caf 0%, #951d95 100%); padding: 36px 32px 28px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
    .header p { margin: 6px 0 0; color: rgba(255,255,255,0.8); font-size: 14px; }
    .body { padding: 36px 32px; text-align: center; }
    .body p { color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 28px; }
    .code-wrap { margin: 0 auto 28px; }
    .code-number { display: inline-block; font-size: 48px; font-weight: 900; color: #8a1caf; letter-spacing: 14px; padding: 16px 24px 16px 38px; background: #f7f0fd; border-radius: 14px; }
    .expiry { color: #888; font-size: 13px; }
    .footer { background: #f9f9fb; padding: 20px 32px; text-align: center; border-top: 1px solid #eee; }
    .footer p { margin: 0; color: #aaa; font-size: 12px; line-height: 1.5; }
</style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Verificá tu cuenta</h1>
        <p>Perfumes Dashboard</p>
    </div>
    <div class="body">
        <p>Usá este código para verificar tu dirección de email. Válido por <strong>15 minutos</strong>.</p>
        <div class="code-wrap">
            <span class="code-number">{{ $code }}</span>
        </div>
        <p class="expiry">Si no solicitaste este código, ignorá este mensaje.</p>
    </div>
    <div class="footer">
        <p>Este código expira en 15 minutos.<br>No compartas este código con nadie.</p>
    </div>
</div>
</body>
</html>
