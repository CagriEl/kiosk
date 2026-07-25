<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Kiosk Günlük Rapor</title>
    <style>
        :root {
            --blue: #1e5a9e;
            --blue-dark: #123a6b;
            --bg: #f0f4f8;
            --card: #fff;
            --text: #1f2937;
            --muted: #6b7280;
            --line: #e5e7eb;
            --green: #047857;
            --cyan: #0e7490;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 24px;
        }
        .wrap { max-width: 920px; margin: 0 auto; }
        header {
            background: linear-gradient(180deg, var(--blue), var(--blue-dark));
            color: #fff;
            border-radius: 16px;
            padding: 22px 26px;
            margin-bottom: 20px;
        }
        header h1 { margin: 0 0 6px; font-size: 1.45rem; }
        header p { margin: 0; opacity: .9; font-size: .95rem; }
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px 20px;
        }
        .card .label { color: var(--muted); font-size: .85rem; font-weight: 600; }
        .card .value { font-size: 2rem; font-weight: 800; margin-top: 6px; letter-spacing: .02em; }
        .card.debt .value { color: var(--blue); }
        .card.avans .value { color: var(--cyan); }
        .card.total .value { color: var(--green); }
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--line);
            font-size: .95rem;
        }
        th {
            background: #eef4fb;
            color: var(--blue-dark);
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        tr:last-child td { border-bottom: 0; }
        td.num { font-variant-numeric: tabular-nums; font-weight: 700; }
        .meta { color: var(--muted); font-size: .85rem; margin: 14px 0 0; }
        @media (max-width: 700px) {
            .cards { grid-template-columns: 1fr; }
            body { padding: 14px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Kiosk günlük işlem raporu</h1>
        <p>T.C. Kırklareli Belediye Başkanlığı · {{ $today['date'] }} · Cihaz: {{ $kioskId }}</p>
    </header>

    <div class="cards">
        <div class="card debt">
            <div class="label">Bugün · Borç sorgulama</div>
            <div class="value">{{ $today['debt_query'] }}</div>
        </div>
        <div class="card avans">
            <div class="label">Bugün · Avans kredi</div>
            <div class="value">{{ $today['avans_credit'] }}</div>
        </div>
        <div class="card total">
            <div class="label">Bugün · Toplam</div>
            <div class="value">{{ $today['total'] }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Borç sorgulama</th>
                <th>Avans kredi</th>
                <th>Toplam</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d.m.Y') }}</td>
                    <td class="num">{{ $row['debt_query'] }}</td>
                    <td class="num">{{ $row['avans_credit'] }}</td>
                    <td class="num">{{ $row['total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Henüz kayıt yok.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="meta">
        Son {{ $days }} gün · Destek: {{ $supportPhone }}
        @if (!empty($yonetim))
            · <a href="{{ route('yonetim.index') }}" style="color:#1e5a9e;font-weight:700;">Yönetime dön</a>
        @endif
    </p>
</div>
</body>
</html>
