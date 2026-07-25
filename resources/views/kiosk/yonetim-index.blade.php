<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Kiosk Yönetim</title>
    <style>
        :root {
            --blue: #1e5a9e; --blue-dark: #123a6b; --bg: #f0f4f8;
            --card: #fff; --text: #1f2937; --muted: #6b7280; --line: #e5e7eb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg); color: var(--text); padding: 24px;
        }
        .wrap { max-width: 860px; margin: 0 auto; }
        header {
            background: linear-gradient(180deg, var(--blue), var(--blue-dark));
            color: #fff; border-radius: 16px; padding: 22px 26px;
            display: flex; justify-content: space-between; gap: 16px; align-items: center;
            margin-bottom: 20px;
        }
        header h1 { margin: 0 0 4px; font-size: 1.35rem; }
        header p { margin: 0; opacity: .9; font-size: .9rem; }
        .logout {
            background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.3);
            border-radius: 10px; padding: 10px 14px; font-weight: 700; cursor: pointer; text-decoration: none;
        }
        .grid { display: grid; grid-template-columns: 1.2fr .8fr; gap: 16px; }
        .card {
            background: var(--card); border: 1px solid var(--line);
            border-radius: 14px; padding: 18px 20px;
        }
        .card h2 { margin: 0 0 12px; font-size: 1.05rem; color: var(--blue-dark); }
        .file {
            display: flex; justify-content: space-between; gap: 12px; align-items: center;
            padding: 12px 0; border-bottom: 1px solid var(--line);
        }
        .file:last-child { border-bottom: 0; }
        .file .name { font-weight: 700; }
        .file .meta { color: var(--muted); font-size: .85rem; margin-top: 2px; }
        a.btn {
            display: inline-block; text-decoration: none; font-weight: 700;
            background: #0ea5e9; color: #04283a; padding: 10px 14px; border-radius: 10px;
            white-space: nowrap;
        }
        a.btn.big {
            display: block; text-align: center; padding: 16px; margin-bottom: 10px;
            background: var(--blue); color: #fff;
        }
        a.btn.secondary { background: #e2e8f0; color: #0f172a; }
        .note { color: var(--muted); font-size: .88rem; line-height: 1.5; margin-top: 12px; }
        @media (max-width: 700px) {
            .grid { grid-template-columns: 1fr; }
            header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div>
            <h1>Kiosk yönetim</h1>
            <p>T.C. Kırklareli Belediye Başkanlığı · Destek {{ $supportPhone }}</p>
        </div>
        <form method="post" action="{{ route('yonetim.logout', absolute: false) }}">
            @csrf
            <button class="logout" type="submit">Çıkış</button>
        </form>
    </header>

    <div class="grid">
        <div class="card">
            <h2>Kurulum / araç dosyaları</h2>
            @forelse ($files as $file)
                <div class="file">
                    <div>
                        <div class="name">{{ $file['label'] }}</div>
                        <div class="meta">{{ $file['name'] }} · {{ number_format($file['size'] / 1024, 1) }} KB</div>
                    </div>
                    <a class="btn" href="{{ route('yonetim.download', $file['name']) }}">İndir</a>
                </div>
            @empty
                <p class="note">Dosya bulunamadı.</p>
            @endforelse
            <p class="note">Dosyalar herkese açık değildir; yalnızca giriş yaptıktan sonra indirilebilir.</p>
        </div>

        <div class="card">
            <h2>Rapor</h2>
            <a class="btn big" href="{{ route('yonetim.report') }}">Günlük işlem raporu</a>
            <p class="note">Borç sorgulama ve avans kredi sayaçları (günlük).</p>
        </div>
    </div>
</div>
</body>
</html>
