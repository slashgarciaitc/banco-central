<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>Acceso · Banco Central</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 15% 10%,#e7edff 0,transparent 35%),#f5f7fa;color:#17243a;font:15px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}.card{width:min(100%,430px);padding:34px;background:#fff;border:1px solid #e6ebf1;border-radius:17px;box-shadow:0 18px 55px #1a2d4c12}.brand{display:flex;align-items:center;gap:12px;margin-bottom:26px;font-weight:750}.mark{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:#315bd0;color:white;font-size:18px}.eyebrow{color:#2857d5;font-size:11px;font-weight:750;letter-spacing:.12em;text-transform:uppercase}.card h1{margin:3px 0 6px;font-size:26px;letter-spacing:-.03em}.intro{margin:0 0 24px;color:#718096;font-size:13px}.field{display:flex;flex-direction:column;gap:6px;margin-bottom:15px}.field label{font-size:12px;font-weight:650;color:#53647b}.field input{min-height:43px;padding:9px 11px;border:1px solid #d8e0e9;border-radius:8px;font:inherit}.field input:focus{outline:3px solid #315bd014;border-color:#7796ee}.button{width:100%;min-height:43px;margin-top:5px;border:0;border-radius:9px;background:#2857d5;color:white;font:650 14px inherit;cursor:pointer}.error{margin-top:5px;color:#b42318;font-size:12px}.foot{margin-top:24px;text-align:center;color:#91a0b1;font-size:11px}
    </style>
</head>
<body>
<main class="card">
    <div class="brand"><span class="mark">₿</span><span>Banco Central</span></div>
    <div class="eyebrow">Acceso administrativo</div>
    <h1>Bienvenido</h1>
    <p class="intro">Inicia sesión con tu usuario administrador de Supabase.</p>
    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus>@error('email')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field"><label for="password">Contraseña</label><input id="password" name="password" type="password" autocomplete="current-password" required>@error('password')<span class="error">{{ $message }}</span>@enderror</div>
        <button class="button" type="submit">Ingresar al panel</button>
    </form>
    <div class="foot">Acceso protegido · Núcleo bancario</div>
</main>
</body>
</html>