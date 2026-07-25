<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kiosk Yönetim · Giriş</title>
    <style>
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(180deg, #1e5a9e, #0a1f38);
            color: #e2e8f0;
            padding: 24px;
        }
        .card {
            width: 100%; max-width: 420px;
            background: #1e293b; border: 1px solid #334155;
            border-radius: 18px; padding: 28px 30px;
        }
        h1 { margin: 0 0 8px; font-size: 1.35rem; color: #fff; }
        p { margin: 0 0 20px; color: #94a3b8; line-height: 1.5; font-size: .95rem; }
        label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: 8px; color: #cbd5e1; }
        input[type=password] {
            width: 100%; box-sizing: border-box;
            padding: 14px 16px; border-radius: 12px; border: 1px solid #475569;
            background: #0f172a; color: #f8fafc; font-size: 1rem;
        }
        button {
            margin-top: 16px; width: 100%; border: 0; border-radius: 12px;
            padding: 14px 16px; font-weight: 700; font-size: 1rem;
            background: #0ea5e9; color: #04283a; cursor: pointer;
        }
        .err { color: #fca5a5; font-size: .9rem; margin: 10px 0 0; }
    </style>
</head>
<body>
<div class="card">
    <h1>Kiosk yönetim</h1>
    <p>Kurulum dosyaları ve günlük rapor. Yetkisiz erişim kapalıdır.</p>
    <form method="post" action="">
        @csrf
        <label for="password">Şifre</label>
        <input id="password" type="password" name="password" autocomplete="current-password" autofocus required>
        @error('password')
            <p class="err">{{ $message }}</p>
        @enderror
        <button type="submit">Giriş yap</button>
    </form>
</div>
</body>
</html>
