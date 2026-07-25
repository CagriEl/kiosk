<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1024, height=768, initial-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Belediye Ödeme Kiosk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        municipal: {
                            50:  '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            300: '#93c5fd', 400: '#60a5fa', 500: '#1e5a9e',
                            600: '#164a85', 700: '#123a6b', 800: '#0f2d52', 900: '#0a1f38',
                        },
                        municipalGray: { 400: '#9ca3af', 500: '#6b7280', 600: '#4b5563', 700: '#374151', 800: '#1f2937' }
                    },
                    fontSize: {
                        'kiosk-xs':   ['0.875rem', { lineHeight: '1.35' }],
                        'kiosk-sm':   ['1rem',     { lineHeight: '1.35' }],
                        'kiosk-base': ['1.125rem', { lineHeight: '1.35' }],
                        'kiosk-lg':   ['1.375rem', { lineHeight: '1.3' }],
                        'kiosk-xl':   ['1.75rem',  { lineHeight: '1.2' }],
                        'kiosk-2xl':  ['2.125rem', { lineHeight: '1.15' }],
                        'kiosk-3xl':  ['2.5rem',   { lineHeight: '1.1' }],
                    }
                }
            }
        }
    </script>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            -webkit-user-select: none; user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        html, body {
            width: 1024px; height: 768px; overflow: hidden;
            margin: 0; padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0f4f8; touch-action: manipulation;
        }
        .kiosk-screen { display: none; width: 1024px; height: 768px; overflow: hidden; }
        .kiosk-screen.active { display: flex; }
        #screen-login.active {
            display: flex;
            flex-direction: column;
        }
        .login-split {
            flex: 1;
            display: grid;
            grid-template-columns: 624px 400px;
            min-height: 0;
        }
        .login-left {
            padding: 1.25rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.85rem;
            min-width: 0;
        }
        .login-numpad {
            background: #fff;
            border-left: 3px solid #93c5fd;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            box-shadow: -4px 0 16px rgba(30, 90, 158, 0.08);
        }
        .touch-btn { transition: transform .1s ease, background-color .15s ease; cursor: pointer; }
        .touch-btn:active { transform: scale(0.97); }
        .numpad-key { transition: transform .08s ease, background-color .1s ease; }
        .numpad-key:active { transform: scale(0.95); background-color: #bfdbfe !important; }
        .debt-checkbox:checked + .debt-card-inner {
            background-color: #dbeafe; border-color: #1e5a9e;
            box-shadow: 0 0 0 3px rgba(30,90,158,.25);
        }
        @keyframes fadeIn { from { opacity:0; transform:translateY(12px) } to { opacity:1; transform:translateY(0) } }
        @keyframes scaleIn { from { opacity:0; transform:scale(.5) } to { opacity:1; transform:scale(1) } }
        @keyframes pulse-ring { 0% { transform:scale(1); opacity:.6 } 100% { transform:scale(1.4); opacity:0 } }
        @keyframes spin { to { transform: rotate(360deg) } }
        .animate-fade-in { animation: fadeIn .4s ease forwards; }
        .animate-scale-in { animation: scaleIn .5s ease forwards; }
        .success-check-ring { animation: pulse-ring 1.5s ease-out infinite; }
        .numpad-grid {
            display: grid;
            grid-template-columns: repeat(3, 112px);
            grid-template-rows: repeat(4, 88px);
            gap: 12px;
        }
        .numpad-key {
            font-size: 1.75rem;
            line-height: 1;
        }
        .numpad-key.action { font-size: 1.05rem; }
        .identity-strip {
            width: 100%;
            background: #fff;
            border: 3px solid #bfdbfe;
            border-radius: 1rem;
            padding: 0.85rem 0.85rem;
            box-shadow: 0 4px 14px rgba(30, 90, 158, 0.08);
            cursor: pointer;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .identity-strip.is-focused {
            border-color: #1e5a9e;
            box-shadow: 0 4px 14px rgba(30, 90, 158, 0.16);
        }
        .digit-row {
            display: flex;
            justify-content: space-between;
            gap: 5px;
        }
        .digit-slot {
            flex: 1;
            min-width: 0;
            height: 56px;
            border: 2px solid #bfdbfe;
            border-radius: 0.65rem;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #123a6b;
        }
        .digit-slot.filled {
            background: #eff6ff;
            border-color: #1e5a9e;
        }
        .digit-slot.active {
            border-color: #1e5a9e;
            box-shadow: 0 0 0 3px rgba(30, 90, 158, 0.2);
            background: #fff;
        }
        .birth-sep {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 12px;
            flex-shrink: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #93c5fd;
        }
        .btn-query-wide {
            width: 100%;
            padding: 1.25rem 1.25rem;
            font-size: 1.375rem;
            letter-spacing: 0.02em;
        }
        .loading-spinner {
            border: 4px solid #dbeafe; border-top-color: #1e5a9e;
            border-radius: 50%; width: 40px; height: 40px;
            animation: spin .8s linear infinite;
        }
        .inactivity-overlay { backdrop-filter: blur(6px); }
        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0; }
        .kiosk-copyable,
        [role="alert"] {
            -webkit-user-select: text;
            user-select: text;
            cursor: text;
        }
        .debt-type-clamp { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .vendor-card.selected { border-color: #1e5a9e; background: #eff6ff; box-shadow: 0 0 0 3px rgba(30,90,158,.2); }
        .water-card-slot {
            width: 280px; height: 180px; margin: 0 auto;
            border: 3px dashed #93c5fd; border-radius: 1.25rem;
            background: linear-gradient(135deg, #eff6ff 0%, #fff 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .abone-digit-row { display: flex; gap: 8px; justify-content: center; }
        .abone-digit {
            width: 52px; height: 64px; border: 2px solid #bfdbfe; border-radius: 0.75rem;
            background: #f8fafc; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: 700; color: #123a6b;
        }
        .abone-digit.filled { background: #eff6ff; border-color: #1e5a9e; }
        .abone-digit.active { border-color: #1e5a9e; box-shadow: 0 0 0 3px rgba(30, 90, 158, 0.2); background: #fff; }
        #debt-list, #water-invoice-list { overflow-y:auto; scrollbar-width:none; }
        #debt-list::-webkit-scrollbar, #water-invoice-list::-webkit-scrollbar { display:none; }

        /* Baylan — genel.7z avans akışı */
        #screen-baylan {
            background:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(14, 116, 144, 0.18), transparent 55%),
                linear-gradient(180deg, #e8f4f8 0%, #f1f5f9 45%, #e2e8f0 100%);
        }
        .baylan-hero {
            text-align: center;
            margin-bottom: 1.25rem;
        }
        .baylan-hero h3 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #0e7490;
            line-height: 1.1;
        }
        .baylan-hero p {
            margin-top: 0.5rem;
            font-size: 1.05rem;
            color: #475569;
        }
        .baylan-status {
            min-height: 7.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem 1.5rem;
            margin: 0 auto 1.5rem;
            max-width: 52rem;
            border-left: 5px solid #0e7490;
            background: rgba(255,255,255,0.72);
            color: #7f1d1d;
            font-size: 1.35rem;
            font-weight: 600;
            line-height: 1.45;
            text-align: center;
        }
        .baylan-status.is-ok {
            border-left-color: #059669;
            color: #064e3b;
        }
        .baylan-status.is-idle { color: #334155; border-left-color: #64748b; }
        .baylan-card-stage {
            width: min(420px, 90%);
            aspect-ratio: 1.55;
            margin: 0 auto 1.75rem;
            position: relative;
            border-radius: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background:
                linear-gradient(145deg, rgba(255,255,255,0.95) 0%, rgba(224,242,254,0.9) 100%);
            border: 3px solid #67e8f9;
            box-shadow: 0 18px 40px rgba(14, 116, 144, 0.16);
            overflow: hidden;
        }
        .baylan-card-stage::before {
            content: '';
            position: absolute;
            inset: -40%;
            background: conic-gradient(from 180deg, transparent, rgba(6,182,212,0.25), transparent 40%);
            animation: baylan-spin 6s linear infinite;
        }
        .baylan-card-stage > * { position: relative; z-index: 1; }
        .baylan-card-icon {
            width: 5.5rem; height: 3.5rem;
            border: 3px solid #0891b2;
            border-radius: 0.65rem;
            margin-bottom: 0.85rem;
            background: linear-gradient(180deg, #ecfeff, #ffffff);
            box-shadow: inset 0 -8px 0 rgba(8,145,178,0.12);
            animation: baylan-pulse 2.2s ease-in-out infinite;
        }
        .baylan-actions {
            width: min(640px, 92%);
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .baylan-btn {
            width: 100%;
            min-height: 5.5rem;
            border-radius: 1rem;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            border: none;
            transition: transform .12s ease, filter .12s ease;
        }
        .baylan-btn:active:not(:disabled) { transform: scale(0.985); }
        .baylan-btn-primary {
            background: linear-gradient(180deg, #0891b2 0%, #0e7490 100%);
            color: #fff;
            box-shadow: 0 10px 24px rgba(14, 116, 144, 0.35);
        }
        .baylan-btn-primary:disabled { opacity: 0.45; box-shadow: none; }
        .baylan-btn-load {
            background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
            color: #fff;
            box-shadow: 0 10px 24px rgba(217, 119, 6, 0.35);
        }
        .baylan-btn-load:disabled { opacity: 0.45; box-shadow: none; }
        .baylan-btn-cancel {
            background: #fff;
            color: #b91c1c;
            border: 3px solid #fecaca;
            font-size: 1.35rem;
            min-height: 4.25rem;
        }
        .baylan-warn {
            margin-top: 1.5rem;
            text-align: center;
            color: #b91c1c;
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: 0.01em;
        }
        .baylan-meta {
            display: none;
            width: min(640px, 92%);
            margin: 0 auto 1rem;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            text-align: center;
        }
        .baylan-meta.visible { display: grid; }
        .baylan-meta dt {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            margin-bottom: 0.2rem;
        }
        .baylan-meta dd {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
        }
        .baylan-test {
            margin-top: 1.25rem;
            text-align: center;
        }
        .baylan-test summary {
            cursor: pointer;
            color: #64748b;
            font-size: 0.9rem;
            list-style: none;
        }
        .baylan-test summary::-webkit-details-marker { display: none; }
        @keyframes baylan-spin { to { transform: rotate(360deg); } }
        @keyframes baylan-pulse {
            0%, 100% { transform: translateY(0); box-shadow: inset 0 -8px 0 rgba(8,145,178,0.12); }
            50% { transform: translateY(-4px); box-shadow: inset 0 -8px 0 rgba(8,145,178,0.22), 0 8px 16px rgba(8,145,178,0.18); }
        }
        #maintenance-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 100;
            width: 1024px;
            height: 768px;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, #1e5a9e 0%, #123a6b 55%, #0a1f38 100%);
            color: #fff;
            text-align: center;
            padding: 2.5rem;
        }
        #maintenance-overlay.is-visible { display: flex; }
        #maintenance-overlay .maint-clock {
            font-size: 3.25rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            font-variant-numeric: tabular-nums;
            margin: 1.25rem 0 0.5rem;
        }
        #maintenance-overlay .maint-badge {
            display: inline-block;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.28);
            border-radius: 999px;
            padding: 0.45rem 1.1rem;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        #maintenance-overlay .maint-title {
            font-size: 2.125rem;
            font-weight: 800;
            line-height: 1.15;
            margin: 1.1rem 0 0.75rem;
            max-width: 820px;
        }
        #maintenance-overlay .maint-msg {
            font-size: 1.2rem;
            line-height: 1.45;
            opacity: 0.92;
            max-width: 720px;
            font-weight: 400;
        }
        #maintenance-overlay .maint-support {
            margin-top: 2rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            min-width: 280px;
        }
    </style>
</head>
<body oncontextmenu="return false;" ondragstart="return false;">

    {{-- EKRAN 1: KARŞILAMA --}}
    <section id="screen-welcome" class="kiosk-screen active flex-col items-center justify-center bg-gradient-to-b from-municipal-500 to-municipal-700 relative">
        <div class="absolute top-0 left-0 flex items-center gap-4 px-8 py-5">
            <img src="{{ asset('images/logo.png') }}" alt="T.C. Kırklareli Belediyesi" class="h-24 w-auto drop-shadow-xl" />
            <div class="text-white">
                <p class="text-kiosk-base font-bold tracking-wide leading-tight">T.C. Kırklareli Belediye Başkanlığı</p>
                <p class="text-kiosk-sm font-medium opacity-90 leading-tight mt-0.5">Akıllı Ödeme Sistemleri</p>
            </div>
        </div>
        <div class="text-center text-white mb-10 animate-fade-in">
            <h1 class="text-kiosk-2xl font-bold mb-3">Ödeme &amp; Sorgulama</h1>
            <p class="text-kiosk-base opacity-90 font-light">Borçlarınızı kolayca görüntüleyin ve ödeyin</p>
        </div>
        <button id="btn-start" type="button" class="touch-btn bg-white text-municipal-600 font-bold text-kiosk-lg px-14 py-6 rounded-2xl shadow-2xl hover:bg-municipal-50" aria-label="Başlamak için dokunun">
            BAŞLAMAK İÇİN DOKUNUN
        </button>
        <p class="absolute bottom-6 text-white/60 text-kiosk-xs">Dokunmatik ekranı kullanarak işleminizi tamamlayın</p>
    </section>

    {{-- EKRAN 1b: HİZMET MENÜSÜ --}}
    <section id="screen-menu" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-municipal-600 text-white px-8 py-3 flex items-center justify-between shrink-0 gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-menu-back" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @include('kiosk.partials.brand', ['screenTitle' => 'Hizmet Seçimi'])
            </div>
            <div class="flex items-center gap-3 shrink-0 bg-white/10 rounded-2xl px-4 py-2.5 border border-white/20">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </div>
                <div class="leading-tight">
                    <p class="text-kiosk-xs font-semibold opacity-90">Destek için</p>
                    <p class="text-kiosk-sm font-bold tracking-wide">444 01 39</p>
                    <p class="text-[0.7rem] opacity-75">nolu hattı arayabilirsiniz</p>
                </div>
            </div>
        </header>
        <div class="flex-1 flex items-center justify-center px-10 gap-8">
            <button id="btn-menu-debt" type="button" class="touch-btn flex-1 max-w-md bg-white border-3 border-municipal-300 rounded-3xl p-10 shadow-xl hover:border-municipal-500 text-left">
                <div class="w-16 h-16 rounded-2xl bg-municipal-100 flex items-center justify-center mb-5">
                    <svg class="w-9 h-9 text-municipal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <h3 class="text-kiosk-xl font-bold text-municipalGray-800 mb-2">Borç Sorgulama</h3>
                <p class="text-kiosk-sm text-municipalGray-600">T.C. Kimlik Numaranızla belediye borçlarınızı görüntüleyin.</p>
            </button>
            <button id="btn-menu-water" type="button" class="touch-btn flex-1 max-w-md bg-white border-3 border-cyan-400 rounded-3xl p-10 shadow-xl hover:border-cyan-600 text-left">
                <div class="w-16 h-16 rounded-2xl bg-cyan-100 flex items-center justify-center mb-5">
                    <svg class="w-9 h-9 text-cyan-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-4 4-6 7-6 10a6 6 0 1012 0c0-3-2-6-6-10z"/></svg>
                </div>
                <h3 class="text-kiosk-xl font-bold text-municipalGray-800 mb-2">Kartlı Su Sayacı</h3>
                <p class="text-kiosk-sm text-municipalGray-600">Baylan kartınızla avans veya kontör yükleyin.</p>
            </button>
        </div>
    </section>

    {{-- EKRAN: SU — MARKA SEÇİMİ --}}
    <section id="screen-water-vendor" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-cyan-700 text-white px-8 py-3 flex items-center gap-3 shrink-0">
            <button id="btn-water-vendor-back" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            @include('kiosk.partials.brand', ['screenTitle' => 'Sayaç Markası'])
        </header>
        <div class="flex-1 flex flex-col items-center justify-center px-10">
            <p class="text-kiosk-base text-municipalGray-600 mb-8 text-center">Baylan işlemine devam etmek için dokunun</p>
            <div class="flex gap-8 w-full max-w-3xl justify-center">
                <button type="button" data-vendor="baylan" id="btn-open-baylan" class="vendor-card touch-btn flex-1 max-w-sm bg-white border-3 border-cyan-300 rounded-3xl p-8 text-center shadow-lg">
                    <div class="text-kiosk-2xl font-black text-cyan-700 mb-2">BAYLAN</div>
                    <p class="text-kiosk-sm text-municipalGray-500">Avans kredi yükleme · NFC kart</p>
                    <p class="text-kiosk-xs text-municipalGray-400 mt-3">Microsoft Edge · Internet Explorer modu</p>
                </button>
            </div>
        </div>
    </section>

    {{-- EKRAN: BAYLAN AVANS KREDİ (genel.7z baylan.aspx akışı) --}}
    <section id="screen-baylan" class="kiosk-screen flex-col">
        <header class="bg-cyan-700 text-white px-8 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-baylan-back" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @include('kiosk.partials.brand', ['screenTitle' => 'BAYLAN · Avans Kredi Yükleme'])
            </div>
            <span id="baylan-step" class="text-kiosk-xs opacity-75 shrink-0">Adım 1</span>
        </header>

        <div class="flex-1 flex flex-col justify-center px-8 py-6 overflow-y-auto">
            <div class="baylan-hero">
                <h3>AVANS KREDİ YÜKLEME</h3>
                <p>Kartınızı okuyucuya yerleştirin, ardından yüklemeyi onaylayın.</p>
            </div>

            <div id="baylan-status" class="baylan-status is-idle" role="status">
                Kartı okuyucuya yerleştirip <strong>Kart Oku</strong> düğmesine basınız.
            </div>

            <div class="baylan-card-stage" aria-hidden="true">
                <div class="baylan-card-icon"></div>
                <p id="baylan-card-caption" class="text-kiosk-sm font-semibold text-cyan-800">NFC kart bekleniyor</p>
            </div>

            <dl id="baylan-meta" class="baylan-meta">
                <div>
                    <dt>Abone</dt>
                    <dd id="baylan-meta-name">—</dd>
                </div>
                <div>
                    <dt>Abone No</dt>
                    <dd id="baylan-meta-abone">—</dd>
                </div>
                <div>
                    <dt>Yüklenecek</dt>
                    <dd id="baylan-meta-tons">—</dd>
                </div>
            </dl>

            <div class="baylan-actions">
                <button id="btn-baylan-read" type="button" class="baylan-btn baylan-btn-primary touch-btn">KART OKU</button>
                <button id="btn-baylan-load" type="button" class="baylan-btn baylan-btn-load touch-btn hidden" disabled>AVANS YÜKLE</button>
                <button id="btn-baylan-cancel" type="button" class="baylan-btn baylan-btn-cancel touch-btn">İPTAL</button>
            </div>

            <p class="baylan-warn">İşleminiz bitene kadar kartı yerinden oynatmayınız.</p>

            <details class="baylan-test">
                <summary>Test: abone no ile dene</summary>
                <div class="mt-3 flex items-center justify-center gap-3 flex-wrap">
                    <input id="input-baylan-abone" type="text" inputmode="numeric" maxlength="8" placeholder="örn. 12345"
                        class="w-40 text-center text-kiosk-base font-bold border-2 border-cyan-300 rounded-xl py-3 px-2 bg-white" />
                    <button id="btn-baylan-abone" type="button" class="touch-btn bg-municipal-600 text-white font-bold px-6 py-3 rounded-xl">ABONE SORGULA</button>
                </div>
                <p class="text-kiosk-xs text-municipalGray-500 mt-2">Demo: 12345 (yükleme OK) · 27126 (ödenmemiş avans → engel)</p>
            </details>

            <div id="baylan-loading" class="hidden mt-4 flex items-center justify-center gap-3">
                <div class="loading-spinner w-8 h-8" style="border-width:3px;border-top-color:#0e7490"></div>
                <span class="text-kiosk-sm text-municipalGray-600">İşlem yapılıyor, lütfen bekleyiniz...</span>
            </div>
        </div>
    </section>

    {{-- EKRAN: SU — İŞLEM TÜRÜ --}}
    <section id="screen-water-action" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-cyan-700 text-white px-8 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-water-action-back" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @include('kiosk.partials.brand', ['screenTitle' => 'İşlem Türü', 'subtitleId' => 'water-vendor-label'])
            </div>
        </header>
        <div class="flex-1 flex flex-col items-center justify-center px-10 gap-5 max-w-2xl mx-auto w-full">
            <button type="button" data-action="invoice" class="water-action-btn touch-btn w-full bg-white border-2 border-municipal-200 rounded-2xl px-8 py-6 text-left shadow-md hover:border-municipal-400">
                <p class="text-kiosk-lg font-bold text-municipalGray-800">Su Faturası Öde</p>
                <p class="text-kiosk-sm text-municipalGray-500 mt-1">Ödenmemiş su ve atık su faturalarınızı ödeyin</p>
            </button>
            <button type="button" data-action="advance" class="water-action-btn touch-btn w-full bg-white border-2 border-amber-200 rounded-2xl px-8 py-6 text-left shadow-md hover:border-amber-400">
                <p class="text-kiosk-lg font-bold text-municipalGray-800">Avans Kredi Yükle</p>
                <p class="text-kiosk-sm text-municipalGray-500 mt-1">Karta kontör yükleyin, 7 gün içinde ödeyin</p>
            </button>
            <button type="button" data-action="kontor" class="water-action-btn touch-btn w-full bg-white border-2 border-cyan-200 rounded-2xl px-8 py-6 text-left shadow-md hover:border-cyan-400">
                <p class="text-kiosk-lg font-bold text-municipalGray-800">Kontör Yükle (Ödemeli)</p>
                <p class="text-kiosk-sm text-municipalGray-500 mt-1">Kredi kartı ile kontör satın alıp karta yazın</p>
            </button>
        </div>
    </section>

    {{-- EKRAN: SU — KART OKUMA --}}
    <section id="screen-water-card" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-cyan-700 text-white px-8 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-water-card-back" type="button" class="touch-btn w-10 h-10 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @include('kiosk.partials.brand', ['screenTitle' => 'Kart Okuma', 'logoClass' => 'h-10'])
            </div>
            <span id="water-step-label" class="text-kiosk-xs opacity-75 shrink-0">Adım 1</span>
        </header>
        <div class="flex-1 grid grid-cols-2 min-h-0">
            <div class="p-8 flex flex-col justify-center gap-5 border-r border-municipal-200">
                <div class="water-card-slot">
                    <svg class="w-16 h-16 text-municipal-400 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    <p class="text-kiosk-sm font-semibold text-municipalGray-700">Kartı okuyucuya yerleştirin</p>
                </div>
                <button id="btn-water-read-card" type="button" class="touch-btn w-full bg-cyan-600 text-white font-bold text-kiosk-base py-4 rounded-2xl shadow-lg hover:bg-cyan-700">KARTI OKUT</button>
                <p class="text-kiosk-xs text-municipalGray-500 text-center">veya test için abone no girin →</p>
                <p id="water-card-error" class="text-kiosk-sm text-red-600 font-medium hidden" role="alert"></p>
                <div id="water-card-loading" class="hidden flex items-center gap-3 justify-center">
                    <div class="loading-spinner w-8 h-8" style="border-width:3px"></div>
                    <span class="text-kiosk-sm text-municipalGray-600">Kart okunuyor...</span>
                </div>
            </div>
            <aside class="p-6 flex flex-col justify-center bg-white">
                <p class="text-kiosk-xs text-municipalGray-500 mb-3 font-medium uppercase tracking-wide text-center">Test Abone No</p>
                <div id="water-abone-row" class="abone-digit-row mb-4" aria-live="polite"></div>
                <input id="input-water-abone" type="text" class="sr-only" maxlength="8" readonly />
                <div class="grid grid-cols-3 gap-2 max-w-xs mx-auto">
                    @foreach (['1','2','3','4','5','6','7','8','9'] as $key)
                    <button type="button" data-water-key="{{ $key }}" class="water-numpad touch-btn bg-municipal-50 hover:bg-municipal-100 text-municipal-700 font-bold text-kiosk-lg py-3 rounded-xl border-2 border-municipal-200">{{ $key }}</button>
                    @endforeach
                    <button type="button" data-water-key="clear" class="water-numpad touch-btn bg-red-50 text-red-600 font-bold text-kiosk-xs py-3 rounded-xl border-2 border-red-200">TEMİZ</button>
                    <button type="button" data-water-key="0" class="water-numpad touch-btn bg-municipal-50 text-municipal-700 font-bold text-kiosk-lg py-3 rounded-xl border-2 border-municipal-200">0</button>
                    <button type="button" data-water-key="back" class="water-numpad touch-btn bg-municipalGray-100 text-municipalGray-700 font-bold text-kiosk-xs py-3 rounded-xl border-2 border-municipalGray-300">←</button>
                </div>
                <button id="btn-water-abone-query" type="button" disabled class="touch-btn mt-4 w-full max-w-xs mx-auto bg-municipal-600 text-white font-bold py-3 rounded-xl disabled:opacity-40">ABONE SORGULA</button>
            </aside>
        </div>
    </section>

    {{-- EKRAN: SU — ABONE ÖZET (avans onay) --}}
    <section id="screen-water-advance" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-cyan-700 text-white px-8 py-3 flex items-center gap-3 shrink-0">
            <button id="btn-water-advance-back" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            @include('kiosk.partials.brand', ['screenTitle' => 'Avans Kredi Yükleme'])
        </header>
        <div class="flex-1 flex flex-col items-center justify-center px-10 text-center max-w-2xl mx-auto">
            <div id="water-advance-info" class="bg-white rounded-3xl border-2 border-amber-200 p-8 shadow-lg w-full mb-8 text-left space-y-3"></div>
            <p class="text-kiosk-sm text-municipalGray-600 mb-6">Kartınız okuyucuda kalsın. Onayladığınızda karta kredi yazılacaktır.</p>
            <button id="btn-water-advance-confirm" type="button" class="touch-btn bg-amber-500 text-white font-bold text-kiosk-lg px-12 py-5 rounded-2xl shadow-xl hover:bg-amber-600">YÜKLE</button>
            <p id="water-advance-error" class="mt-4 text-kiosk-sm text-red-600 hidden" role="alert"></p>
        </div>
    </section>

    {{-- EKRAN: SU — FATURA LİSTESİ --}}
    <section id="screen-water-invoices" class="kiosk-screen bg-gray-50">
        <header class="bg-cyan-700 text-white px-8 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-water-invoices-back" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @include('kiosk.partials.brand', ['screenTitle' => 'Su Faturaları', 'subtitleId' => 'water-invoice-subscriber'])
            </div>
        </header>
        <div class="flex-1 flex overflow-hidden min-h-0">
            <div class="flex-1 px-6 py-4 overflow-hidden flex flex-col min-w-0">
                <div id="water-invoice-list" class="flex-1 min-h-0 space-y-2 overflow-y-auto" role="list"></div>
            </div>
            <aside class="w-[290px] shrink-0 bg-white border-l-2 border-cyan-200 flex flex-col items-center justify-center px-5 py-6">
                <p class="text-kiosk-xs text-municipalGray-500 mb-1">Seçilen Toplam</p>
                <p id="water-invoice-total" class="text-kiosk-xl font-bold text-cyan-700 mb-5">0,00 ₺</p>
                <button id="btn-water-pay-invoice" type="button" disabled class="touch-btn w-full bg-cyan-600 text-white font-bold text-kiosk-sm py-5 rounded-2xl disabled:opacity-40">BANKA KARTI İLE ÖDE</button>
                <p id="water-invoice-error" class="mt-3 text-kiosk-xs text-red-600 text-center hidden" role="alert"></p>
            </aside>
        </div>
    </section>

    {{-- EKRAN: SU — KONTÖR SEÇİMİ --}}
    <section id="screen-water-kontor" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-cyan-700 text-white px-8 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-water-kontor-back" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @include('kiosk.partials.brand', ['screenTitle' => 'Kontör Yükleme', 'subtitleId' => 'water-kontor-subscriber'])
            </div>
        </header>
        <div class="flex-1 flex flex-col items-center justify-center px-10">
            <p class="text-kiosk-base text-municipalGray-600 mb-6">Yüklemek istediğiniz kontör miktarını (ton) seçiniz</p>
            <div class="flex items-center gap-4 mb-6">
                <button id="btn-kontor-minus" type="button" class="touch-btn w-14 h-14 rounded-xl bg-municipal-100 text-municipal-700 font-bold text-kiosk-xl">−</button>
                <div class="text-center min-w-[120px]">
                    <p id="water-kontor-tons" class="text-kiosk-3xl font-bold text-cyan-700">5</p>
                    <p class="text-kiosk-xs text-municipalGray-500">ton</p>
                </div>
                <button id="btn-kontor-plus" type="button" class="touch-btn w-14 h-14 rounded-xl bg-municipal-100 text-municipal-700 font-bold text-kiosk-xl">+</button>
            </div>
            <p id="water-kontor-amount" class="text-kiosk-2xl font-bold text-municipalGray-800 mb-8">—</p>
            <button id="btn-water-kontor-pay" type="button" disabled class="touch-btn bg-cyan-600 text-white font-bold text-kiosk-lg px-14 py-5 rounded-2xl shadow-xl disabled:opacity-40">BANKA KARTI İLE ÖDE</button>
            <p id="water-kontor-error" class="mt-4 text-kiosk-sm text-red-600 hidden" role="alert"></p>
        </div>
    </section>

    {{-- EKRAN 2: VATANDAŞ GİRİŞİ — sol: uzun numara alanı, sağ: numaratör --}}
    <section id="screen-login" class="kiosk-screen bg-gray-50">
        <header class="bg-municipal-600 text-white px-8 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-back-welcome" type="button" class="touch-btn w-10 h-10 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @include('kiosk.partials.brand', ['screenTitle' => 'Kimlik Bilgileri', 'logoClass' => 'h-10'])
            </div>
            <span class="text-kiosk-xs opacity-75 shrink-0">Adım 1 / 2</span>
        </header>
        <div class="login-split">
            <div class="login-left">
                <div>
                    <h3 class="text-kiosk-xl font-bold text-municipalGray-800 mb-2">Borç Sorgulama</h3>
                    <p id="identity-hint" class="text-kiosk-sm text-municipalGray-600 leading-snug">
                        T.C. Kimlik No ve doğum tarihinizi giriniz. Doğum tarihi, yetkisiz sorguları engellemek içindir.
                    </p>
                </div>
                <div id="strip-tc" class="identity-strip is-focused" data-focus="tc" role="button" tabindex="0">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <p id="identity-strip-label" class="text-kiosk-xs text-municipalGray-500 font-medium uppercase tracking-wide">T.C. Kimlik No</p>
                        <button id="btn-reveal-tc" type="button"
                            class="touch-btn flex items-center gap-2 px-3 py-1.5 rounded-xl bg-municipal-50 border-2 border-municipal-200 text-municipal-700 hover:bg-municipal-100"
                            aria-label="T.C. Kimlik No’yu göster (basılı tutun)"
                            title="Kontrol için basılı tutun">
                            <svg id="icon-eye-open" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg id="icon-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                            <span class="text-kiosk-xs font-semibold">Göster</span>
                        </button>
                    </div>
                    <div id="digit-row" class="digit-row" aria-live="polite"></div>
                </div>
                <div id="strip-birth" class="identity-strip" data-focus="birth" role="button" tabindex="0">
                    <p class="text-kiosk-xs text-municipalGray-500 font-medium uppercase tracking-wide mb-2">Doğum Tarihi (Gün / Ay / Yıl)</p>
                    <div id="birth-digit-row" class="digit-row" aria-live="polite"></div>
                </div>
                <input id="input-identity" type="text" class="sr-only" maxlength="11" readonly aria-label="T.C. Kimlik No" />
                <input id="input-birth" type="text" class="sr-only" maxlength="8" readonly aria-label="Doğum Tarihi" />
                <p id="login-error" class="text-kiosk-sm text-red-600 font-medium hidden" role="alert"></p>
                <button id="btn-query" type="button" disabled
                    class="touch-btn btn-query-wide bg-municipal-600 text-white font-bold rounded-2xl shadow-xl hover:bg-municipal-700 disabled:opacity-40">
                    BORÇLARI SORGULA
                </button>
                @if (config('kiosk.enable_test_query'))
                <button id="btn-test-query" type="button"
                    class="touch-btn w-full py-3 text-kiosk-sm font-semibold text-municipal-600 bg-municipal-50 border-2 border-municipal-200 rounded-xl hover:bg-municipal-100">
                    Test sorgusu (API yok)
                </button>
                @endif
                <div id="login-loading" class="hidden flex items-center gap-3">
                    <div class="loading-spinner w-8 h-8" style="border-width:3px"></div>
                    <span class="text-kiosk-sm text-municipalGray-600">Sorgulanıyor, lütfen bekleyiniz...</span>
                </div>
            </div>
            <aside id="numpad-panel" class="login-numpad" role="group" aria-label="Sayısal klavye">
                <div class="numpad-grid">
                    @foreach (['1','2','3','4','5','6','7','8','9'] as $key)
                    <button type="button" data-key="{{ $key }}" class="numpad-key touch-btn bg-municipal-50 hover:bg-municipal-100 text-municipal-700 font-bold rounded-xl border-2 border-municipal-200">{{ $key }}</button>
                    @endforeach
                    <button type="button" data-key="clear" class="numpad-key action touch-btn bg-red-50 hover:bg-red-100 text-red-600 font-bold rounded-xl border-2 border-red-200">TEMİZLE</button>
                    <button type="button" data-key="0" class="numpad-key touch-btn bg-municipal-50 hover:bg-municipal-100 text-municipal-700 font-bold rounded-xl border-2 border-municipal-200">0</button>
                    <button type="button" data-key="backspace" class="numpad-key action touch-btn bg-municipalGray-100 hover:bg-municipalGray-200 text-municipalGray-700 font-bold rounded-xl border-2 border-municipalGray-400">← SİL</button>
                </div>
            </aside>
        </div>
    </section>

    {{-- EKRAN: ABONELİK SEÇİMİ (aynı TC’de birden fazla kayıt) --}}
    <section id="screen-accounts" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-municipal-600 text-white px-8 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-back-accounts" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @include('kiosk.partials.brand', ['screenTitle' => 'Abonelik Seçimi', 'subtitleId' => 'accounts-subtitle'])
            </div>
            <span class="text-kiosk-xs opacity-75 shrink-0">Adım 1b / 2</span>
        </header>
        <div class="flex-1 px-8 py-6 overflow-y-auto">
            <p class="text-kiosk-sm text-municipalGray-600 mb-4">Bu T.C. Kimlik No’ya birden fazla abonelik kayıtlı. Devam etmek için birini seçiniz.</p>
            <div id="accounts-list" class="space-y-3 max-w-4xl mx-auto" role="list"></div>
            <p id="accounts-error" class="mt-4 text-kiosk-sm text-red-600 hidden" role="alert"></p>
        </div>
    </section>

    {{-- EKRAN 3: BORÇ LİSTESİ --}}
    <section id="screen-debts" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-municipal-600 text-white px-8 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-back-login" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @include('kiosk.partials.brand', ['screenTitle' => 'Borç Listesi', 'subtitleId' => 'citizen-name'])
            </div>
            <span class="text-kiosk-xs opacity-75 shrink-0">Adım 2 / 2</span>
        </header>
        <div class="flex-1 flex overflow-hidden min-h-0">
            <div class="flex-1 px-6 py-4 overflow-hidden flex flex-col min-w-0">
                <div class="flex items-center justify-between mb-3 gap-2">
                    <p class="text-kiosk-sm text-municipalGray-600">Borçlarınız</p>
                    <button id="btn-select-all" type="button" class="touch-btn text-kiosk-xs font-semibold text-municipal-600 bg-municipal-50 px-4 py-2 rounded-xl border-2 border-municipal-200 shrink-0">TÜMÜNÜ SEÇ</button>
                </div>
                <div id="debt-list" class="flex-1 min-h-0 space-y-2" role="list"></div>
            </div>
            <aside class="w-[290px] shrink-0 bg-amber-50 border-l-2 border-amber-300 flex flex-col items-center justify-center px-5 py-6 shadow-inner">
                <div class="w-16 h-16 rounded-full bg-amber-100 border-2 border-amber-300 flex items-center justify-center mb-4">
                    <svg class="w-9 h-9 text-amber-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <p class="text-kiosk-sm font-bold text-amber-900 text-center leading-snug mb-3">Şu anda ödeme yapılamamaktadır</p>
                <p class="text-kiosk-xs text-municipalGray-700 text-center leading-relaxed mb-4">
                    Bu ekran yalnızca borç sorgulama için kullanılmaktadır.
                </p>
                <div class="w-full rounded-2xl bg-white border-2 border-amber-200 px-4 py-4 text-center">
                    <p class="text-[0.7rem] text-municipalGray-500 mb-1.5">Borçlarınızı şu adresten ödeyebilirsiniz</p>
                    <p class="text-kiosk-xs font-bold text-municipal-700 break-all leading-snug">e-belediye.kirklareli.bel.tr</p>
                </div>
                {{-- Ödeme geçici kapalı; JS uyumluluğu için gizli alanlar --}}
                <p id="selected-total" class="hidden">0,00 ₺</p>
                <p id="selected-count" class="hidden">0 borç seçildi</p>
                <button id="btn-pay-bank" type="button" class="hidden" tabindex="-1" aria-hidden="true" disabled></button>
                <p id="payment-error" class="hidden" role="alert"></p>
            </aside>
        </div>
    </section>

    {{-- EKRAN 4: BAŞARILI --}}
    <section id="screen-success" class="kiosk-screen flex-col items-center justify-center bg-gradient-to-b from-green-50 to-white relative">
        <div class="absolute top-0 left-0 flex items-center gap-3 px-8 py-5">
            <img src="{{ asset('images/logo.png') }}" alt="T.C. Kırklareli Belediyesi" class="h-16 w-auto" />
            <div>
                <p class="text-kiosk-sm font-bold tracking-wide leading-tight text-municipal-800">T.C. Kırklareli Belediye Başkanlığı</p>
                <p class="text-kiosk-xs text-municipalGray-600 leading-tight mt-0.5">Akıllı Ödeme Sistemleri</p>
            </div>
        </div>
        <div class="relative mb-8 animate-scale-in">
            <div class="success-check-ring absolute inset-0 rounded-full bg-green-400/30"></div>
            <div class="w-32 h-32 rounded-full bg-green-500 flex items-center justify-center shadow-2xl relative z-10">
                <svg class="w-20 h-20 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
        </div>
        <h2 id="success-title" class="text-kiosk-xl font-bold text-green-700 mb-4 text-center">Ödemeniz Başarıyla Alınmıştır</h2>
        <p id="success-message" class="text-kiosk-base text-municipalGray-600 text-center">Makbuzunuz Yazdırılıyor...</p>
        <div class="mt-10 flex items-center gap-4 text-kiosk-sm text-municipalGray-500">
            <div class="loading-spinner w-8 h-8" style="border-width:3px;border-top-color:#16a34a"></div>
            <span id="success-countdown">7 saniye içinde ana ekrana dönülecek</span>
        </div>
    </section>

    {{-- BANKA ÖDEME MODALI --}}
    <div id="bank-payment-modal" class="fixed inset-0 z-40 hidden items-center justify-center inactivity-overlay bg-black/60" role="dialog" aria-modal="true">
        <div class="bg-white rounded-2xl shadow-2xl w-[560px] border-2 border-municipal-300 overflow-hidden">
            <div class="bg-municipal-600 text-white px-8 py-5 text-center">
                <h3 class="text-kiosk-lg font-bold">Banka Kartı ile Ödeme</h3>
            </div>
            <div class="px-8 py-8 text-center">
                <div class="w-24 h-16 mx-auto mb-5 rounded-xl bg-municipal-50 border-2 border-municipal-200 flex items-center justify-center">
                    <svg class="w-14 h-10 text-municipal-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 64 48">
                        <rect x="2" y="6" width="60" height="36" rx="4" stroke-width="2"/>
                        <rect x="2" y="14" width="60" height="8" fill="currentColor" opacity="0.15" stroke="none"/>
                        <rect x="36" y="28" width="12" height="8" rx="2" stroke-width="2"/>
                    </svg>
                </div>
                <p class="text-kiosk-sm text-municipalGray-600 mb-2">Ödenecek Tutar</p>
                <p id="bank-modal-total" class="text-kiosk-2xl font-bold text-municipal-700 mb-5">0,00 ₺</p>
                <p id="bank-modal-instruction" class="text-kiosk-base text-municipalGray-700 leading-relaxed mb-6">
                    Lütfen banka kartınızı <strong>yanınızdaki POS cihazına</strong> okutunuz.<br>
                    İşlem tamamlandığında aşağıdaki butona dokunun.
                </p>
                <div id="bank-modal-loading" class="hidden flex items-center justify-center gap-3 mb-4">
                    <div class="loading-spinner w-8 h-8" style="border-width:3px"></div>
                    <span class="text-kiosk-sm text-municipalGray-600">Ödeme kaydediliyor...</span>
                </div>
                <p id="bank-modal-error" class="text-kiosk-sm text-red-600 mb-4 hidden" role="alert"></p>
                <div class="flex gap-3">
                    <button id="btn-cancel-bank" type="button" class="touch-btn flex-1 bg-municipalGray-100 text-municipalGray-700 font-bold text-kiosk-sm py-4 rounded-2xl border-2 border-municipalGray-400">İPTAL</button>
                    <button id="btn-confirm-bank" type="button" class="touch-btn flex-1 bg-municipal-600 text-white font-bold text-kiosk-sm py-4 rounded-2xl shadow-xl hover:bg-municipal-700">ÖDEMEYİ ONAYLA</button>
                </div>
            </div>
        </div>
    </div>

    {{-- HAREKETSİZLİK UYARISI --}}
    <div id="inactivity-modal" class="fixed inset-0 z-50 hidden items-center justify-center inactivity-overlay bg-black/50" role="dialog" aria-modal="true">
        <div class="bg-white rounded-2xl shadow-2xl px-10 py-8 text-center w-[560px] border-2 border-municipal-300">
            <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-5">
                <svg class="w-9 h-9 text-amber-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-kiosk-lg font-bold text-municipalGray-800 mb-3">İşleme devam etmek istiyor musunuz?</h3>
            <p class="text-kiosk-sm text-municipalGray-600 mb-6">
                <span id="inactivity-countdown" class="font-bold text-municipal-600 text-kiosk-base">15</span> saniye içinde oturum sonlandırılacak
            </p>
            <button id="btn-continue-session" type="button" class="touch-btn bg-municipal-600 text-white font-bold text-kiosk-base px-10 py-5 rounded-2xl shadow-xl hover:bg-municipal-700 w-full">EVET, DEVAM ET</button>
        </div>
    </div>

    {{-- SİSTEM KAPALI / BELSİS OFFLINE — tam ekran (manuel / kritik) --}}
    <div id="offline-overlay" class="fixed inset-0 z-50 hidden items-center justify-center inactivity-overlay bg-black/70" role="alertdialog" aria-modal="true" aria-labelledby="offline-title">
        <div class="bg-white rounded-2xl shadow-2xl px-10 py-9 text-center w-[600px] border-2 border-red-200">
            <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-5">
                <svg class="w-9 h-9 text-red-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <h3 id="offline-title" class="text-kiosk-lg font-bold text-municipalGray-800 mb-3">Sistem Geçici Olarak Kullanılamıyor</h3>
            <p id="offline-message" class="text-kiosk-sm text-municipalGray-600 mb-5 leading-relaxed">
                Belediye ödeme sistemi şu an erişilemiyor. Lütfen daha sonra tekrar deneyiniz.
            </p>
            <div class="bg-municipal-50 border-2 border-municipal-200 rounded-2xl px-5 py-4 mb-6">
                <p class="text-kiosk-xs text-municipalGray-500 mb-1">Destek hattı</p>
                <p id="offline-support-phone" class="text-kiosk-xl font-bold text-municipal-700 tracking-wide">{{ config('kiosk.support_phone') }}</p>
            </div>
            <div class="flex gap-3">
                <button id="btn-offline-dismiss" type="button" class="touch-btn flex-1 bg-municipalGray-100 text-municipalGray-700 font-bold text-kiosk-sm py-5 rounded-2xl border-2 border-municipalGray-300">YİNE DE DEVAM ET</button>
                <button id="btn-offline-retry" type="button" class="touch-btn flex-1 bg-municipal-600 text-white font-bold text-kiosk-sm py-5 rounded-2xl shadow-xl hover:bg-municipal-700">TEKRAR DENE</button>
            </div>
        </div>
    </div>

    {{-- Hafif uyarı bandı (Belsis erişilemiyor ama ekranı kilitlemez) --}}
    <div id="health-banner" class="fixed top-0 left-0 right-0 z-40 hidden px-6 py-3 bg-amber-500 text-white text-center shadow-lg">
        <p id="health-banner-text" class="text-kiosk-sm font-semibold">Belediye sistemi şu an yanıt vermiyor. Destek: {{ config('kiosk.support_phone') }}</p>
        <button id="btn-health-banner-close" type="button" class="absolute right-4 top-1/2 -translate-y-1/2 touch-btn w-10 h-10 rounded-lg bg-white/20 hover:bg-white/30 font-bold">×</button>
    </div>

    {{-- Gece bakımı (bilgisayar saati): 00:44 uyarı → 00:45–07:00 kilit --}}
    <div id="maintenance-overlay" role="alertdialog" aria-modal="true" aria-labelledby="maint-title" aria-live="polite">
        <div class="absolute top-0 left-0 flex items-center gap-4 px-8 py-5">
            <img src="{{ asset('images/logo.png') }}" alt="" class="h-20 w-auto drop-shadow-xl" />
            <div class="text-left">
                <p class="text-kiosk-base font-bold tracking-wide leading-tight">T.C. Kırklareli Belediye Başkanlığı</p>
                <p class="text-kiosk-sm font-medium opacity-90 leading-tight mt-0.5">Akıllı Ödeme Sistemleri</p>
            </div>
        </div>
        <span id="maint-badge" class="maint-badge">Sistem Bakımı</span>
        <h2 id="maint-title" class="maint-title">Sistem bakımdadır</h2>
        <p id="maint-msg" class="maint-msg">Sabah 07:00’de yeniden hizmete açılacaktır.</p>
        <div id="maint-clock" class="maint-clock" aria-hidden="true">00:00:00</div>
        <p id="maint-until" class="text-kiosk-sm opacity-80 mt-1"></p>
        <div class="maint-support">
            <p class="text-kiosk-xs opacity-80 mb-1">Destek hattı</p>
            <p class="text-kiosk-xl font-bold tracking-wide">{{ config('kiosk.support_phone') }}</p>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        function resolveApiBase() {
            const path = window.location.pathname.replace(/\/kiosk\/?$/, '').replace(/\/?$/, '');
            return window.location.origin + path + '/api/kiosk';
        }

        const API_BASE = resolveApiBase();
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
        const KIOSK_ID = @json(config('kiosk.id'));
        const KIOSK_API_KEY = @json(config('kiosk.api_key') ?: '');
        const KIOSK_SUPPORT_PHONE = @json(config('kiosk.support_phone'));
        const KIOSK_ENABLE_TEST = @json((bool) config('kiosk.enable_test_query'));
        const BAYLAN_IE_URL = @json(config('belsis.baylan_ie_url'));
        const BAYLAN_IE_PROTOCOL = 'baylan-ie:open';
        const MAINT = {
            warnHour: {{ (int) config('kiosk.maintenance.warn_hour') }},
            warnMinute: {{ (int) config('kiosk.maintenance.warn_minute') }},
            startHour: {{ (int) config('kiosk.maintenance.start_hour') }},
            startMinute: {{ (int) config('kiosk.maintenance.start_minute') }},
            endHour: {{ (int) config('kiosk.maintenance.end_hour') }},
            endMinute: {{ (int) config('kiosk.maintenance.end_minute') }},
        };

        /**
         * Windows kiosk başlatıcısı kullanılmadığında ilk kullanıcı dokunuşuyla
         * tarayıcı arayüzünü gizler. Gerçek cihazda Chrome ayrıca --kiosk ile açılır.
         */
        function ensureBrowserFullscreen() {
            if (document.fullscreenElement || !document.documentElement.requestFullscreen) {
                return;
            }

            document.documentElement.requestFullscreen({ navigationUI: 'hide' }).catch(() => {
                // Tarayıcı politikası izin vermiyorsa Windows --kiosk başlatıcısı devralır.
            });
        }

        /**
         * BAYLAN → Edge IE modunda avans kredi sayfası:
         * http://belapp.belediye.local/.../baylan.aspx?...
         *
         * baylan-ie:open protokolü, kiosk PC kurulumunda kaydedilen launcher'ı
         * Edge --ie-mode-force --kiosk ile bu adrese yönlendirir.
         */
        function openBaylanInEdgeIe() {
            ensureBrowserFullscreen();
            if (!BAYLAN_IE_URL) {
                console.warn('BAYLAN_IE_URL tanımsız');
                return;
            }

            // Günlük avans kredi sayacı (başarısız olsa da akışı engellemez)
            try {
                fetch(`${API_BASE}/stats/event`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: kioskHeaders(),
                    body: JSON.stringify({ type: 'avans_credit' }),
                    keepalive: true,
                }).catch(() => {});
            } catch (_) { /* ignore */ }

            // AutoLaunchProtocolsFromOrigins politikası yüklüyse Chrome onay
            // penceresi göstermeden protokolü doğrudan çalıştırır.
            try {
                window.location.assign(BAYLAN_IE_PROTOCOL);
            } catch (_) { /* ignore */ }
        }

        function kioskHeaders(extra = {}) {
            const headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Kiosk-Id': KIOSK_ID,
                ...extra,
            };
            if (KIOSK_API_KEY) headers['X-Kiosk-Key'] = KIOSK_API_KEY;
            return headers;
        }

        async function apiRequest(url, options = {}) {
            try {
                const { headers: extraHeaders, ...rest } = options;
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    ...rest,
                    headers: kioskHeaders(extraHeaders || {}),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const detail = data.sonucKodu ? ` (${data.sonucKodu})` : '';
                    const err = new Error((data.message || `Sunucu hatası (${res.status})`) + detail);
                    err.status = res.status;
                    err.payload = data;
                    throw err;
                }
                return data;
            } catch (err) {
                if (err instanceof TypeError) {
                    throw new Error('API bağlantısı kurulamadı. Adres: ' + url);
                }
                throw err;
            }
        }

        async function checkSystemHealth() {
            try {
                const res = await fetch(`${API_BASE}/health`, {
                    headers: kioskHeaders(),
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                // Belsis erişilemiyorsa tam ekran kilidi yok — üstte uyarı bandı
                if (!res.ok || data.status === 'degraded' || data.belsis === 'down') {
                    showHealthBanner(
                        data.message || ('Belediye sistemi şu an yanıt vermiyor. Destek: ' + (data.support_phone || KIOSK_SUPPORT_PHONE))
                    );
                    hideOfflineOverlay();
                    return false;
                }
                hideHealthBanner();
                hideOfflineOverlay();
                return true;
            } catch (err) {
                // API’nin kendisi yoksa (sunucu düşmüş) tam ekran göster
                showOfflineOverlay(
                    'Kiosk sunucusuna bağlanılamadı. Lütfen daha sonra tekrar deneyiniz.',
                    KIOSK_SUPPORT_PHONE
                );
                return false;
            }
        }

        function showOfflineOverlay(message, phone) {
            const el = document.getElementById('offline-overlay');
            if (message) document.getElementById('offline-message').textContent = message;
            if (phone) document.getElementById('offline-support-phone').textContent = phone;
            el.classList.remove('hidden');
            el.classList.add('flex');
        }

        function hideOfflineOverlay() {
            const el = document.getElementById('offline-overlay');
            el.classList.add('hidden');
            el.classList.remove('flex');
        }

        function showHealthBanner(message) {
            const banner = document.getElementById('health-banner');
            if (message) document.getElementById('health-banner-text').textContent = message;
            banner.classList.remove('hidden');
        }

        function hideHealthBanner() {
            document.getElementById('health-banner').classList.add('hidden');
        }

        async function fetchCitizen(accountNo, searchType, birthDate) {
            if (KIOSK_ENABLE_TEST && isTestIdentity(accountNo)) {
                return getTestCitizen(accountNo, birthDate);
            }
            const params = new URLSearchParams();
            if (searchType) params.set('searchType', searchType);
            if (birthDate) params.set('birthDate', birthDate);
            const qs = params.toString() ? `?${params}` : '';
            return apiRequest(`${API_BASE}/citizen/${accountNo}${qs}`);
        }

        async function fetchDebts(accountNo, searchType, gensicilNo, aboneNo) {
            if (KIOSK_ENABLE_TEST && isTestIdentity(accountNo)) {
                return getTestDebts();
            }
            const params = new URLSearchParams();
            if (searchType) params.set('searchType', searchType);
            if (gensicilNo) params.set('gensicilNo', gensicilNo);
            if (aboneNo) params.set('aboneNo', aboneNo);
            if (session.queryToken) params.set('queryToken', session.queryToken);
            const qs = params.toString() ? `?${params}` : '';
            return apiRequest(`${API_BASE}/debts/${accountNo}${qs}`);
        }

        /** Yerel test TC — API çağrılmaz (doğum: 01/01/1990) */
        const TEST_TC = '11111111110';
        const TEST_BIRTH = '01011990';

        function isTestIdentity(identityNo) {
            return String(identityNo || '').replace(/\D/g, '') === TEST_TC;
        }

        function getTestCitizen(identityNo, birthDate) {
            const digits = String(birthDate || '').replace(/\D/g, '');
            if (digits !== TEST_BIRTH) {
                throw new Error(
                    digits === ''
                        ? 'Doğum tarihinizi gün/ay/yıl olarak giriniz (örn. 01/01/1990).'
                        : 'Doğum tarihi eşleşmedi. Lütfen kontrol edip tekrar deneyiniz.'
                );
            }
            return {
                identityNo: identityNo,
                fullName: 'Test Vatandaş',
                adi: 'Test',
                soyadi: 'Vatandaş',
                gensicilNo: '10001',
                sicilNo: '10001',
                aboneNo: '200200',
                address: 'Test Mah. Demo Cad. No:1 Kırklareli',
                totalDebt: 375.50,
                needsSelection: false,
                accounts: [],
            };
        }

        function getTestDebts() {
            return {
                debts: [
                    {
                        id: 'test-debt-1',
                        type: 'Su abonelik borcu',
                        period: '2026 / 03',
                        amount: 185.75,
                        dueDate: '2026-04-15',
                        meta: {
                            groupKey: 'test-su-2026-03',
                            groupTitle: 'Su Aboneliği',
                            aboneNo: '200200',
                            modulBilgisi: 'Test modülü',
                        },
                    },
                    {
                        id: 'test-debt-2',
                        type: 'Çevre temizlik vergisi',
                        period: '2026 / 1. dönem',
                        amount: 189.75,
                        dueDate: '2026-06-30',
                        meta: {
                            groupKey: 'test-ctv-2026-1',
                            groupTitle: 'Çevre Temizlik Vergisi',
                            aboneNo: '200200',
                        },
                    },
                ],
            };
        }

        async function initiateBankPayment(identityNo, selectedDebtIds, searchType, gensicilNo, aboneNo) {
            return apiRequest(`${API_BASE}/payment/bank`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({
                    identityNo,
                    debtIds: selectedDebtIds,
                    searchType: searchType || undefined,
                    gensicilNo: gensicilNo || undefined,
                    aboneNo: aboneNo || undefined,
                }),
            });
        }

        async function confirmPayment(transactionId, identityNo, debtIds, searchType, gensicilNo, aboneNo) {
            return apiRequest(`${API_BASE}/payment/${transactionId}/confirm`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({
                    identityNo,
                    debtIds,
                    searchType: searchType || undefined,
                    gensicilNo: gensicilNo || undefined,
                    aboneNo: aboneNo || undefined,
                }),
            });
        }

        function subscriberDisplayName(s) {
            if (!s) return 'Kayıt';
            return s.fullName || [s.adi, s.soyadi].filter(Boolean).join(' ').trim() || ('No: ' + (s.sicilNo || s.aboneNo || ''));
        }

        function extractSubscriber(payload) {
            if (!payload || typeof payload !== 'object') return null;
            if (payload.subscriber && typeof payload.subscriber === 'object') return payload.subscriber;
            if (payload.aboneNo) return payload;
            return null;
        }

        async function waterFetchSubscriber(vendor, aboneNo) {
            return apiRequest(`${API_BASE}/water/${vendor}/subscriber/${aboneNo}`);
        }

        async function waterCardRead(vendor, aboneNo) {
            return apiRequest(`${API_BASE}/water/card-read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ vendor, aboneNo: aboneNo || null }),
            });
        }

        async function waterFetchInvoices(vendor, aboneNo) {
            return apiRequest(`${API_BASE}/water/${vendor}/invoices/${aboneNo}`);
        }

        async function waterCalculateKontor(vendor, aboneNo, tons) {
            return apiRequest(`${API_BASE}/water/calculate-kontor`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ vendor, aboneNo, tons }),
            });
        }

        async function waterPayInvoices(vendor, aboneNo, invoiceIds) {
            return apiRequest(`${API_BASE}/water/pay-invoices`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ vendor, aboneNo, invoiceIds }),
            });
        }

        async function waterConfirmInvoicePayment(transactionId, vendor, aboneNo, invoiceIds) {
            return apiRequest(`${API_BASE}/water/pay-invoices/${transactionId}/confirm`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ vendor, aboneNo, invoiceIds }),
            });
        }

        async function waterAdvanceLoad(vendor, aboneNo) {
            return apiRequest(`${API_BASE}/water/advance-load`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ vendor, aboneNo }),
            });
        }

        async function waterInitiateKontor(vendor, aboneNo, tons) {
            return apiRequest(`${API_BASE}/water/kontor/pay`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ vendor, aboneNo, tons }),
            });
        }

        async function waterConfirmKontor(transactionId, vendor, aboneNo, tons) {
            return apiRequest(`${API_BASE}/water/kontor/${transactionId}/confirm`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ vendor, aboneNo, tons }),
            });
        }

        function formatCurrency(amount) {
            const n = Number(amount);
            return (Number.isFinite(n) ? n : 0).toLocaleString('tr-TR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }) + ' ₺';
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' });
        }

        const SCREENS = {
            welcome:       document.getElementById('screen-welcome'),
            menu:          document.getElementById('screen-menu'),
            login:         document.getElementById('screen-login'),
            accounts:      document.getElementById('screen-accounts'),
            debts:         document.getElementById('screen-debts'),
            success:       document.getElementById('screen-success'),
            waterVendor:   document.getElementById('screen-water-vendor'),
            baylan:        document.getElementById('screen-baylan'),
            waterAction:   document.getElementById('screen-water-action'),
            waterCard:     document.getElementById('screen-water-card'),
            waterAdvance:  document.getElementById('screen-water-advance'),
            waterInvoices: document.getElementById('screen-water-invoices'),
            waterKontor:   document.getElementById('screen-water-kontor'),
        };

        const KONTOR_OPTIONS = [5, 10, 20, 30, 40, 50];

        const session = {
            citizen: null, debts: [], selectedIds: new Set(),
            currentScreen: 'welcome', pendingPayment: null, accounts: [],
            queryToken: null,
        };
        const water = {
            vendor: null, action: null, subscriber: null,
            invoices: [], selectedInvoiceIds: new Set(),
            kontorIndex: 0, kontorAmount: 0, pendingPayment: null,
        };
        const INACTIVITY_MS = 45000, WARNING_COUNTDOWN_S = 15, SUCCESS_REDIRECT_MS = 7000;
        let inactivityTimer, warningInterval, successTimer, successCountdownIv;
        // Gerçek Belsis çağrıları (login + odemeYap + makbuzSorgula) canlı ağda 45 sn'yi
        // aşabilir — bu sürede hareketsizlik zaman aşımı devreye girip modalı kapatıp
        // session.pendingPayment'i sıfırlarsa, kullanıcı "ÖDEMEYİ ONAYLA"ya bastığında
        // istek zaten arka planda bitmiş/bitmemiş olsa da ekranda hiçbir şey değişmez.
        let paymentInFlight = false;

        function showScreen(name) {
            Object.values(SCREENS).forEach(el => el.classList.remove('active'));
            SCREENS[name].classList.add('active');
            session.currentScreen = name;
            resetInactivityTimer();
            if (name === 'login') {
                renderIdentityDisplay();
                renderBirthDisplay();
            }
        }

        function resetWaterSession() {
            water.vendor = null; water.action = null; water.subscriber = null;
            water.invoices = []; water.selectedInvoiceIds.clear();
            water.kontorIndex = 0; water.kontorAmount = 0; water.pendingPayment = null;
            setWaterAboneValue('');
            document.getElementById('water-card-error').classList.add('hidden');
            document.getElementById('water-advance-error').classList.add('hidden');
            document.getElementById('water-invoice-error').classList.add('hidden');
            document.getElementById('water-kontor-error').classList.add('hidden');
            document.querySelectorAll('.vendor-card').forEach(el => el.classList.remove('selected'));
            resetBaylanScreen();
        }

        function resetBaylanScreen() {
            const status = document.getElementById('baylan-status');
            const meta = document.getElementById('baylan-meta');
            const btnRead = document.getElementById('btn-baylan-read');
            const btnLoad = document.getElementById('btn-baylan-load');
            const inputAbone = document.getElementById('input-baylan-abone');
            if (!status || !btnRead) return;

            status.className = 'baylan-status is-idle';
            status.innerHTML = 'Kartı okuyucuya yerleştirip <strong>Kart Oku</strong> düğmesine basınız.';
            meta.classList.remove('visible');
            document.getElementById('baylan-meta-name').textContent = '—';
            document.getElementById('baylan-meta-abone').textContent = '—';
            document.getElementById('baylan-meta-tons').textContent = '—';
            document.getElementById('baylan-card-caption').textContent = 'NFC kart bekleniyor';
            document.getElementById('baylan-step').textContent = 'Adım 1';
            document.getElementById('baylan-loading').classList.add('hidden');
            btnRead.classList.remove('hidden');
            btnRead.disabled = false;
            btnLoad.classList.add('hidden');
            btnLoad.disabled = true;
            if (inputAbone) inputAbone.value = '';
        }

        function setBaylanReady(subscriber) {
            water.subscriber = subscriber;
            const tons = subscriber.advanceTons || 3;
            const status = document.getElementById('baylan-status');
            status.className = 'baylan-status';
            status.textContent = 'Sn. ' + subscriberDisplayName(subscriber)
                + ', ' + subscriber.aboneNo + ' numaralı aboneliğe ' + tons
                + ' ton avans kredi yüklenecektir. Kartınızı yerinden almayınız.';

            document.getElementById('baylan-meta').classList.add('visible');
            document.getElementById('baylan-meta-name').textContent = subscriberDisplayName(subscriber);
            document.getElementById('baylan-meta-abone').textContent = subscriber.aboneNo;
            document.getElementById('baylan-meta-tons').textContent = tons + ' ton';
            document.getElementById('baylan-card-caption').textContent = 'Kart okundu · ' + (subscriber.sayacNo || '');
            document.getElementById('baylan-step').textContent = 'Adım 2';

            document.getElementById('btn-baylan-read').classList.add('hidden');
            const btnLoad = document.getElementById('btn-baylan-load');
            btnLoad.classList.remove('hidden');
            btnLoad.disabled = false;
        }

        async function processBaylanCardRead(aboneNo, mode = 'card') {
            const status = document.getElementById('baylan-status');
            const loading = document.getElementById('baylan-loading');
            const btnRead = document.getElementById('btn-baylan-read');
            status.className = 'baylan-status is-idle';
            status.textContent = mode === 'manual' ? 'Abone sorgulanıyor...' : 'Kart okunuyor...';
            loading.classList.remove('hidden');
            btnRead.disabled = true;
            onUserActivity();

            try {
                let subscriber;
                if (mode === 'manual' && aboneNo) {
                    subscriber = extractSubscriber(await waterFetchSubscriber('baylan', aboneNo));
                } else {
                    const data = await waterCardRead('baylan', aboneNo || null);
                    subscriber = extractSubscriber(data);
                }
                if (!subscriber?.aboneNo) {
                    throw new Error('Kart okunamadı. Lütfen kartınızı yerleştirip tekrar deneyiniz.');
                }
                if ((subscriber.unpaidAdvance || 0) > 0) {
                    throw new Error('Zaten avans kontör yüklenmiş. Yükleme yapılamaz!');
                }
                setBaylanReady(subscriber);
            } catch (err) {
                status.className = 'baylan-status';
                status.textContent = err.message || 'Kart okunamadı.';
                btnRead.classList.remove('hidden');
                btnRead.disabled = false;
                document.getElementById('btn-baylan-load').classList.add('hidden');
                document.getElementById('baylan-meta').classList.remove('visible');
                document.getElementById('baylan-card-caption').textContent = 'NFC kart bekleniyor';
            } finally {
                loading.classList.add('hidden');
            }
        }

        async function processBaylanLoad() {
            const status = document.getElementById('baylan-status');
            const loading = document.getElementById('baylan-loading');
            const btnLoad = document.getElementById('btn-baylan-load');
            if (!water.subscriber?.aboneNo) return;

            btnLoad.disabled = true;
            loading.classList.remove('hidden');
            status.className = 'baylan-status is-idle';
            status.textContent = 'Kontör kartınıza yazılıyor...';
            onUserActivity();

            try {
                const result = await waterAdvanceLoad('baylan', water.subscriber.aboneNo);
                const tons = result.loadedTons || water.subscriber.advanceTons || 3;
                status.className = 'baylan-status is-ok';
                status.innerHTML = 'Yüklenen avans kredi miktarı <strong>' + tons
                    + '</strong> tondur.<br>7 gün içerisinde ' + tons
                    + ' ton su tutarını ödemediğiniz takdirde gecikme zammı uygulanacaktır.<br>İyi günler dileriz.';
                document.getElementById('baylan-step').textContent = 'Tamamlandı';
                document.getElementById('baylan-card-caption').textContent = 'Yükleme başarılı';
                btnLoad.classList.add('hidden');
                setTimeout(() => {
                    showWaterSuccess('Avans Yüklendi', result.message || status.textContent.replace(/<[^>]+>/g, ' '));
                }, 2800);
            } catch (err) {
                status.className = 'baylan-status';
                status.textContent = err.message || 'Kontör yazımı gerçekleştirilemedi.';
                btnLoad.disabled = false;
            } finally {
                loading.classList.add('hidden');
            }
        }

        function resetSession() {
            session.citizen = null; session.debts = []; session.selectedIds.clear();
            session.queryToken = null;
            setTcRevealed(false);
            setLoginFocus('tc');
            setIdentityValue('');
            setBirthValue('');
            document.getElementById('login-error').classList.add('hidden');
            document.getElementById('btn-query').disabled = true;
            document.getElementById('login-loading').classList.add('hidden');
            document.getElementById('debt-list').innerHTML = '';
            document.getElementById('selected-total').textContent = '0,00 ₺';
            document.getElementById('selected-count').textContent = '0 borç seçildi';
            document.getElementById('btn-pay-bank').disabled = true;
            document.getElementById('payment-error').classList.add('hidden');
            session.pendingPayment = null;
            resetWaterSession();
            closeBankModal();
            document.getElementById('btn-select-all').textContent = 'TÜMÜNÜ SEÇ';
            clearTimeout(successTimer); clearInterval(successCountdownIv);
            closeInactivityModal();
            renderIdentityDisplay();
            renderBirthDisplay();
        }

        function goHome() { resetSession(); showScreen('welcome'); }

        function vendorLabel(v) {
            return v === 'baylan' ? 'BAYLAN' : v === 'metlab' ? 'METLAB' : '';
        }

        function showWaterSuccess(title, message) {
            document.getElementById('success-title').textContent = title;
            document.getElementById('success-message').textContent = message;
            showSuccessScreen();
        }

        function afterWaterCardRead(subscriber) {
            if (!subscriber || !subscriber.aboneNo) {
                throw new Error('Abone bilgisi alınamadı. Lütfen tekrar deneyiniz.');
            }
            water.subscriber = subscriber;
            const label = subscriberDisplayName(subscriber) + ' — Abone ' + subscriber.aboneNo;

            if (water.action === 'invoice') {
                loadWaterInvoices();
            } else if (water.action === 'advance') {
                renderWaterAdvanceInfo();
                showScreen('waterAdvance');
            } else if (water.action === 'kontor') {
                water.kontorIndex = 0;
                document.getElementById('water-kontor-subscriber').textContent = label;
                updateKontorDisplay();
                showScreen('waterKontor');
            }
        }

        async function loadWaterInvoices() {
            if (!water.subscriber?.aboneNo) {
                throw new Error('Abone bilgisi bulunamadı.');
            }
            try {
                const { invoices } = await waterFetchInvoices(water.vendor, water.subscriber.aboneNo);
                water.invoices = invoices || [];
                water.selectedInvoiceIds.clear();
                document.getElementById('water-invoice-subscriber').textContent =
                    subscriberDisplayName(water.subscriber) + ' — Abone ' + water.subscriber.aboneNo;
                renderWaterInvoiceList();
                showScreen('waterInvoices');
            } catch (err) {
                document.getElementById('water-card-error').textContent = err.message;
                document.getElementById('water-card-error').classList.remove('hidden');
                showScreen('waterCard');
            }
        }

        function renderWaterAdvanceInfo() {
            const s = water.subscriber;
            if (!s) return;
            document.getElementById('water-advance-info').innerHTML = `
                <p class="text-kiosk-lg font-bold text-municipalGray-800">${subscriberDisplayName(s)}</p>
                <p class="text-kiosk-sm text-municipalGray-600">Abone No: <strong>${s.aboneNo}</strong> · ${vendorLabel(water.vendor)}</p>
                <p class="text-kiosk-sm text-municipalGray-600">Sayaç: ${s.sayacNo} · Kart: ${s.kartTipi}</p>
                <p class="text-kiosk-sm text-municipalGray-600">Mevcut kredi: <strong>${s.anaKredi} ton</strong> (yedek: ${s.yedekKredi} ton)</p>
                <p class="text-kiosk-sm text-amber-700 font-medium mt-4">Yüklenecek avans: ${s.advanceTons || 3} kontör</p>
            `;
        }

        function renderWaterInvoiceList() {
            const container = document.getElementById('water-invoice-list');
            if (!water.invoices.length) {
                container.innerHTML = '<p class="text-kiosk-sm text-municipalGray-500 text-center py-8">Ödenmemiş fatura bulunamadı.</p>';
                updateWaterInvoicePanel();
                return;
            }
            container.innerHTML = water.invoices.map(inv => `
                <label class="block cursor-pointer" role="listitem">
                    <input type="checkbox" class="water-invoice-cb sr-only" data-id="${inv.id}" />
                    <div class="water-inv-card flex items-center gap-3 bg-white border-2 border-municipalGray-400/30 rounded-2xl px-4 py-3 shadow-sm">
                        <div class="w-9 h-9 rounded-lg border-2 border-cyan-300 flex items-center justify-center shrink-0 water-inv-check">
                            <svg class="w-5 h-5 text-cyan-600 hidden water-check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-kiosk-sm font-bold text-municipalGray-800">${inv.type}</p>
                            <p class="text-kiosk-xs text-municipalGray-500">${inv.period} · ${formatDate(inv.dueDate)}</p>
                        </div>
                        <p class="text-kiosk-base font-bold text-cyan-700 shrink-0">${formatCurrency(inv.amount)}</p>
                    </div>
                </label>
            `).join('');
            container.querySelectorAll('.water-invoice-cb').forEach(cb => {
                cb.addEventListener('change', () => {
                    const card = cb.closest('label').querySelector('.water-inv-card');
                    if (cb.checked) {
                        water.selectedInvoiceIds.add(cb.dataset.id);
                        card.classList.add('border-cyan-500', 'bg-cyan-50');
                        card.querySelector('.water-check-icon').classList.remove('hidden');
                    } else {
                        water.selectedInvoiceIds.delete(cb.dataset.id);
                        card.classList.remove('border-cyan-500', 'bg-cyan-50');
                        card.querySelector('.water-check-icon').classList.add('hidden');
                    }
                    updateWaterInvoicePanel();
                    onUserActivity();
                });
            });
            updateWaterInvoicePanel();
        }

        function updateWaterInvoicePanel() {
            const selected = water.invoices.filter(i => water.selectedInvoiceIds.has(i.id));
            const total = selected.reduce((s, i) => s + i.amount, 0);
            document.getElementById('water-invoice-total').textContent = formatCurrency(total);
            document.getElementById('btn-water-pay-invoice').disabled = selected.length === 0;
        }

        function updateKontorDisplay() {
            if (!water.subscriber?.aboneNo || !water.vendor) return;
            const tons = KONTOR_OPTIONS[water.kontorIndex];
            document.getElementById('water-kontor-tons').textContent = tons;
            document.getElementById('btn-kontor-minus').disabled = water.kontorIndex <= 0;
            document.getElementById('btn-kontor-plus').disabled = water.kontorIndex >= KONTOR_OPTIONS.length - 1;
            waterCalculateKontor(water.vendor, water.subscriber.aboneNo, tons)
                .then(calc => {
                    water.kontorAmount = calc.amount;
                    document.getElementById('water-kontor-amount').textContent = formatCurrency(calc.amount);
                    document.getElementById('btn-water-kontor-pay').disabled = false;
                })
                .catch(err => {
                    document.getElementById('water-kontor-error').textContent = err.message;
                    document.getElementById('water-kontor-error').classList.remove('hidden');
                });
        }

        const inputWaterAbone = document.getElementById('input-water-abone');
        const waterAboneRow = document.getElementById('water-abone-row');
        const MAX_ABONE = 8;

        function renderWaterAboneDisplay() {
            const val = inputWaterAbone.value;
            let html = '';
            const slots = Math.max(5, val.length || 5);
            for (let i = 0; i < slots; i++) {
                html += `<div class="abone-digit${val[i] ? ' filled' : ''}">${val[i] || ''}</div>`;
            }
            waterAboneRow.innerHTML = html;
            document.getElementById('btn-water-abone-query').disabled = val.length < 4;
        }

        function setWaterAboneValue(val) {
            inputWaterAbone.value = val.slice(0, MAX_ABONE);
            renderWaterAboneDisplay();
        }

        function digitFromKeyEvent(e) {
            if (e.key >= '0' && e.key <= '9') return e.key;
            const numpadMatch = e.code.match(/^Numpad([0-9])$/);
            if (numpadMatch) return numpadMatch[1];
            const digitMatch = e.code.match(/^Digit([0-9])$/);
            if (digitMatch) return digitMatch[1];
            return null;
        }

        function isCopyableElement(el) {
            return el && el.closest && el.closest('.kiosk-copyable, [role="alert"]');
        }

        function selectionIsCopyable() {
            const sel = window.getSelection();
            if (!sel || sel.isCollapsed) return false;
            const node = sel.anchorNode;
            const el = node?.nodeType === Node.TEXT_NODE ? node.parentElement : node;
            return isCopyableElement(el);
        }

        function handleLoginKeyboard(e) {
            const digit = digitFromKeyEvent(e);
            if (digit !== null) {
                e.preventDefault();
                appendLoginDigit(digit);
                loginError.classList.add('hidden');
                onUserActivity();
                return true;
            }
            if (e.key === 'Backspace') {
                e.preventDefault();
                backspaceLoginDigit();
                loginError.classList.add('hidden');
                onUserActivity();
                return true;
            }
            if (e.key === 'Delete' || e.key === 'Escape') {
                e.preventDefault();
                clearActiveLoginField();
                loginError.classList.add('hidden');
                onUserActivity();
                return true;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                if (!btnQuery.disabled) btnQuery.click();
                return true;
            }
            return false;
        }

        function handleWaterAboneKeyboard(e) {
            const digit = digitFromKeyEvent(e);
            if (digit !== null) {
                e.preventDefault();
                if (inputWaterAbone.value.length < MAX_ABONE) {
                    setWaterAboneValue(inputWaterAbone.value + digit);
                }
                document.getElementById('water-card-error').classList.add('hidden');
                onUserActivity();
                return true;
            }
            if (e.key === 'Backspace') {
                e.preventDefault();
                setWaterAboneValue(inputWaterAbone.value.slice(0, -1));
                document.getElementById('water-card-error').classList.add('hidden');
                onUserActivity();
                return true;
            }
            if (e.key === 'Delete' || e.key === 'Escape') {
                e.preventDefault();
                setWaterAboneValue('');
                document.getElementById('water-card-error').classList.add('hidden');
                onUserActivity();
                return true;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                const btn = document.getElementById('btn-water-abone-query');
                if (!btn.disabled) btn.click();
                return true;
            }
            return false;
        }

        async function processWaterCardRead(aboneNo, mode = 'card') {
            const errEl = document.getElementById('water-card-error');
            const loading = document.getElementById('water-card-loading');
            errEl.classList.add('hidden');

            if (!water.vendor) {
                errEl.textContent = 'Lütfen önce sayaç markası seçiniz.';
                errEl.classList.remove('hidden');
                return;
            }
            if (!water.action) {
                errEl.textContent = 'Lütfen önce işlem türü seçiniz.';
                errEl.classList.remove('hidden');
                return;
            }

            loading.classList.remove('hidden');
            onUserActivity();
            try {
                let subscriber;
                if (mode === 'manual' && aboneNo) {
                    subscriber = extractSubscriber(await waterFetchSubscriber(water.vendor, aboneNo));
                } else {
                    const data = await waterCardRead(water.vendor, aboneNo || null);
                    subscriber = extractSubscriber(data);
                }
                afterWaterCardRead(subscriber);
            } catch (err) {
                errEl.textContent = err.message;
                errEl.classList.remove('hidden');
            } finally {
                loading.classList.add('hidden');
            }
        }

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer); clearInterval(warningInterval);
            if (session.currentScreen === 'welcome' || paymentInFlight) return;
            inactivityTimer = setTimeout(showInactivityWarning, INACTIVITY_MS);
        }

        function showInactivityWarning() {
            const modal = document.getElementById('inactivity-modal');
            modal.classList.remove('hidden'); modal.classList.add('flex');
            let countdown = WARNING_COUNTDOWN_S;
            document.getElementById('inactivity-countdown').textContent = countdown;
            warningInterval = setInterval(() => {
                countdown--;
                document.getElementById('inactivity-countdown').textContent = countdown;
                if (countdown <= 0) { clearInterval(warningInterval); goHome(); }
            }, 1000);
        }

        function closeInactivityModal() {
            const modal = document.getElementById('inactivity-modal');
            modal.classList.add('hidden'); modal.classList.remove('flex');
            clearInterval(warningInterval);
        }

        function onUserActivity() {
            if (document.getElementById('inactivity-modal').classList.contains('flex')) closeInactivityModal();
            resetInactivityTimer();
        }

        const inputIdentity = document.getElementById('input-identity');
        const inputBirth = document.getElementById('input-birth');
        const digitRow = document.getElementById('digit-row');
        const birthDigitRow = document.getElementById('birth-digit-row');
        const btnQuery = document.getElementById('btn-query');
        const loginError = document.getElementById('login-error');
        const btnRevealTc = document.getElementById('btn-reveal-tc');
        const stripTc = document.getElementById('strip-tc');
        const stripBirth = document.getElementById('strip-birth');
        const TC_DIGITS = 11;
        const BIRTH_DIGITS = 8;
        let tcRevealed = false;
        let loginFocus = 'tc'; // 'tc' | 'birth'

        /** KVKK: ilk 3 + ortası yıldız + son 3 (örn. 123*****789) */
        function maskTc(tc) {
            const digits = String(tc || '').replace(/\D/g, '');
            if (digits.length !== TC_DIGITS) return digits;
            return digits.slice(0, 3) + '*****' + digits.slice(8);
        }

        function displayTcDigit(val, index) {
            const ch = val[index];
            if (!ch) return '';
            if (tcRevealed) return ch;
            if (index >= 3 && index <= 7) return '*';
            return ch;
        }

        function formatBirthForApi(digits) {
            const d = String(digits || '').replace(/\D/g, '');
            if (d.length !== 8) return '';
            return d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4, 8);
        }

        function setLoginFocus(focus) {
            loginFocus = focus === 'birth' ? 'birth' : 'tc';
            stripTc.classList.toggle('is-focused', loginFocus === 'tc');
            stripBirth.classList.toggle('is-focused', loginFocus === 'birth');
            renderIdentityDisplay();
            renderBirthDisplay();
        }

        function updateRevealTcUi() {
            const eyeOpen = document.getElementById('icon-eye-open');
            const eyeOff = document.getElementById('icon-eye-off');
            if (tcRevealed) {
                eyeOpen.classList.add('hidden');
                eyeOff.classList.remove('hidden');
                btnRevealTc.classList.add('bg-municipal-100', 'border-municipal-500');
                btnRevealTc.setAttribute('aria-pressed', 'true');
            } else {
                eyeOpen.classList.remove('hidden');
                eyeOff.classList.add('hidden');
                btnRevealTc.classList.remove('bg-municipal-100', 'border-municipal-500');
                btnRevealTc.setAttribute('aria-pressed', 'false');
            }
        }

        function setTcRevealed(revealed) {
            if (tcRevealed === revealed) return;
            tcRevealed = revealed;
            updateRevealTcUi();
            renderIdentityDisplay();
        }

        function renderIdentityDisplay() {
            const val = inputIdentity.value;
            let html = '';
            for (let i = 0; i < TC_DIGITS; i++) {
                const ch = val[i] || '';
                const shown = displayTcDigit(val, i);
                const cls = ['digit-slot'];
                if (ch) cls.push('filled');
                if (loginFocus === 'tc' && i === val.length && val.length < TC_DIGITS) cls.push('active');
                html += `<div class="${cls.join(' ')}" aria-hidden="true">${shown}</div>`;
            }
            digitRow.innerHTML = html;
        }

        function renderBirthDisplay() {
            const val = inputBirth.value;
            let html = '';
            for (let i = 0; i < BIRTH_DIGITS; i++) {
                if (i === 2 || i === 4) html += '<span class="birth-sep" aria-hidden="true">/</span>';
                const ch = val[i] || '';
                const cls = ['digit-slot'];
                if (ch) cls.push('filled');
                if (loginFocus === 'birth' && i === val.length && val.length < BIRTH_DIGITS) cls.push('active');
                html += `<div class="${cls.join(' ')}" aria-hidden="true">${ch}</div>`;
            }
            birthDigitRow.innerHTML = html;
        }

        function updateQueryButton() {
            btnQuery.disabled = !(
                inputIdentity.value.trim().length === TC_DIGITS
                && inputBirth.value.trim().length === BIRTH_DIGITS
            );
        }

        function setIdentityValue(val) {
            inputIdentity.value = val.replace(/\D/g, '').slice(0, TC_DIGITS);
            renderIdentityDisplay();
            if (inputIdentity.value.length === TC_DIGITS && loginFocus === 'tc') {
                setLoginFocus('birth');
            }
            updateQueryButton();
        }

        function setBirthValue(val) {
            inputBirth.value = val.replace(/\D/g, '').slice(0, BIRTH_DIGITS);
            renderBirthDisplay();
            updateQueryButton();
        }

        function appendLoginDigit(digit) {
            if (loginFocus === 'birth') {
                if (inputBirth.value.length < BIRTH_DIGITS) {
                    setBirthValue(inputBirth.value + digit);
                }
                return;
            }
            if (inputIdentity.value.length < TC_DIGITS) {
                setIdentityValue(inputIdentity.value + digit);
            }
        }

        function backspaceLoginDigit() {
            if (loginFocus === 'birth') {
                if (inputBirth.value.length > 0) {
                    setBirthValue(inputBirth.value.slice(0, -1));
                } else {
                    setLoginFocus('tc');
                }
                return;
            }
            setIdentityValue(inputIdentity.value.slice(0, -1));
        }

        function clearActiveLoginField() {
            if (loginFocus === 'birth') setBirthValue('');
            else setIdentityValue('');
        }

        stripTc.addEventListener('click', (e) => {
            if (e.target.closest('#btn-reveal-tc')) return;
            setLoginFocus('tc');
            onUserActivity();
        });
        stripBirth.addEventListener('click', () => {
            setLoginFocus('birth');
            onUserActivity();
        });

        // Basılı tutunca göster, bırakınca tekrar maskele
        ['pointerdown', 'touchstart', 'mousedown'].forEach((evt) => {
            btnRevealTc.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                setTcRevealed(true);
                onUserActivity();
            });
        });
        ['pointerup', 'pointercancel', 'pointerleave', 'touchend', 'touchcancel', 'mouseup', 'mouseleave'].forEach((evt) => {
            btnRevealTc.addEventListener(evt, (e) => {
                e.stopPropagation();
                setTcRevealed(false);
            });
        });
        window.addEventListener('blur', () => setTcRevealed(false));
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) setTcRevealed(false);
        });

        document.querySelectorAll('.numpad-key').forEach(key => {
            key.addEventListener('click', () => {
                const action = key.dataset.key;
                if (action === 'clear') clearActiveLoginField();
                else if (action === 'backspace') backspaceLoginDigit();
                else appendLoginDigit(action);
                loginError.classList.add('hidden');
                onUserActivity();
            });
        });

        document.getElementById('btn-start').addEventListener('click', () => {
            ensureBrowserFullscreen();
            showScreen('menu');
            onUserActivity();
        });
        document.getElementById('btn-menu-back').addEventListener('click', goHome);
        document.getElementById('btn-menu-debt').addEventListener('click', () => { showScreen('login'); onUserActivity(); });
        document.getElementById('btn-menu-water').addEventListener('click', () => { resetWaterSession(); showScreen('waterVendor'); onUserActivity(); });

        document.querySelectorAll('.vendor-card').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.vendor-card').forEach(el => el.classList.remove('selected'));
                btn.classList.add('selected');
                water.vendor = btn.dataset.vendor;
                water.action = null;
                water.subscriber = null;
                onUserActivity();

                if (water.vendor !== 'baylan') {
                    return;
                }

                openBaylanInEdgeIe();
                setTimeout(() => btn.classList.remove('selected'), 400);
            });
        });

        document.getElementById('btn-water-vendor-back').addEventListener('click', () => showScreen('menu'));
        document.getElementById('btn-water-action-back').addEventListener('click', () => showScreen('waterVendor'));
        document.getElementById('btn-baylan-back').addEventListener('click', () => { resetBaylanScreen(); showScreen('waterVendor'); });
        document.getElementById('btn-baylan-cancel').addEventListener('click', () => { resetBaylanScreen(); showScreen('waterVendor'); });
        document.getElementById('btn-baylan-read').addEventListener('click', () => {
            const abone = document.getElementById('input-baylan-abone').value.trim();
            processBaylanCardRead(abone || null, 'card');
        });
        document.getElementById('btn-baylan-abone').addEventListener('click', () => {
            const abone = document.getElementById('input-baylan-abone').value.trim();
            if (abone.length >= 4) processBaylanCardRead(abone, 'manual');
        });
        document.getElementById('btn-baylan-load').addEventListener('click', () => processBaylanLoad());
        document.getElementById('input-baylan-abone').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('btn-baylan-abone').click();
            }
        });

        document.querySelectorAll('.water-action-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                water.action = btn.dataset.action;
                document.getElementById('water-step-label').textContent =
                    water.action === 'invoice' ? 'Fatura Öde' : water.action === 'advance' ? 'Avans Yükle' : 'Kontör Yükle';
                showScreen('waterCard');
                onUserActivity();
            });
        });

        document.getElementById('btn-water-card-back').addEventListener('click', () => showScreen('waterAction'));
        document.getElementById('btn-water-read-card').addEventListener('click', () => processWaterCardRead(inputWaterAbone.value.trim() || null, 'card'));
        document.getElementById('btn-water-abone-query').addEventListener('click', () => {
            const abone = inputWaterAbone.value.trim();
            if (abone.length >= 4) processWaterCardRead(abone, 'manual');
        });

        document.querySelectorAll('.water-numpad').forEach(key => {
            key.addEventListener('click', () => {
                const action = key.dataset.waterKey;
                let val = inputWaterAbone.value;
                if (action === 'clear') val = '';
                else if (action === 'back') val = val.slice(0, -1);
                else if (val.length < MAX_ABONE) val += action;
                setWaterAboneValue(val);
                document.getElementById('water-card-error').classList.add('hidden');
                onUserActivity();
            });
        });

        document.getElementById('btn-water-advance-back').addEventListener('click', () => showScreen('waterCard'));
        document.getElementById('btn-water-advance-confirm').addEventListener('click', async () => {
            const errEl = document.getElementById('water-advance-error');
            errEl.classList.add('hidden');
            const btn = document.getElementById('btn-water-advance-confirm');
            btn.disabled = true;
            try {
                const result = await waterAdvanceLoad(water.vendor, water.subscriber.aboneNo);
                showWaterSuccess('Avans Yüklendi', result.message);
            } catch (err) {
                errEl.textContent = err.message;
                errEl.classList.remove('hidden');
                btn.disabled = false;
            }
        });

        document.getElementById('btn-water-invoices-back').addEventListener('click', () => showScreen('waterCard'));
        document.getElementById('btn-water-pay-invoice').addEventListener('click', async () => {
            const ids = [...water.selectedInvoiceIds];
            if (!ids.length) return;
            const errEl = document.getElementById('water-invoice-error');
            errEl.classList.add('hidden');
            const total = water.invoices.filter(i => water.selectedInvoiceIds.has(i.id)).reduce((s, i) => s + i.amount, 0);
            try {
                const payment = await waterPayInvoices(water.vendor, water.subscriber.aboneNo, ids);
                water.pendingPayment = { transactionId: payment.transactionId, invoiceIds: ids, mode: 'invoice' };
                openBankModal(total);
            } catch (err) {
                errEl.textContent = err.message;
                errEl.classList.remove('hidden');
            }
        });

        document.getElementById('btn-water-kontor-back').addEventListener('click', () => showScreen('waterCard'));
        document.getElementById('btn-kontor-minus').addEventListener('click', () => {
            if (water.kontorIndex > 0) { water.kontorIndex--; updateKontorDisplay(); onUserActivity(); }
        });
        document.getElementById('btn-kontor-plus').addEventListener('click', () => {
            if (water.kontorIndex < KONTOR_OPTIONS.length - 1) { water.kontorIndex++; updateKontorDisplay(); onUserActivity(); }
        });
        document.getElementById('btn-water-kontor-pay').addEventListener('click', async () => {
            const tons = KONTOR_OPTIONS[water.kontorIndex];
            const errEl = document.getElementById('water-kontor-error');
            errEl.classList.add('hidden');
            try {
                const payment = await waterInitiateKontor(water.vendor, water.subscriber.aboneNo, tons);
                water.pendingPayment = { transactionId: payment.transactionId, tons, mode: 'kontor' };
                openBankModal(payment.total);
            } catch (err) {
                errEl.textContent = err.message;
                errEl.classList.remove('hidden');
            }
        });

        document.getElementById('btn-back-welcome').addEventListener('click', () => showScreen('menu'));

        btnQuery.addEventListener('click', async () => {
            const identityNo = inputIdentity.value.trim();
            const birthDate = formatBirthForApi(inputBirth.value);
            if (identityNo.length !== 11 || birthDate === '') return;
            btnQuery.disabled = true;
            document.getElementById('login-loading').classList.remove('hidden');
            loginError.classList.add('hidden');
            onUserActivity();
            try {
                const citizen = await fetchCitizen(identityNo, 'tc', birthDate);
                session.citizen = citizen;
                session.queryToken = citizen.queryToken || null;
                session.accounts = Array.isArray(citizen.accounts) ? citizen.accounts : [];
                session.selectedIds.clear();
                session.debts = [];

                if (citizen.needsSelection && session.accounts.length > 1) {
                    renderAccountsList();
                    document.getElementById('accounts-subtitle').textContent =
                        subscriberDisplayName(citizen) + ' — T.C.: ' + maskTc(identityNo);
                    showScreen('accounts');
                } else {
                    await loadDebtsForCitizen(citizen);
                }
            } catch (err) {
                loginError.textContent = err.message;
                loginError.classList.remove('hidden');
                updateQueryButton();
            } finally {
                document.getElementById('login-loading').classList.add('hidden');
            }
        });

        const btnTestQuery = document.getElementById('btn-test-query');
        if (btnTestQuery) {
            btnTestQuery.addEventListener('click', () => {
                setIdentityValue(TEST_TC);
                setBirthValue(TEST_BIRTH);
                setLoginFocus('birth');
                loginError.classList.add('hidden');
                onUserActivity();
                btnQuery.click();
            });
        }

        function renderAccountsList() {
            const container = document.getElementById('accounts-list');
            const errEl = document.getElementById('accounts-error');
            errEl.classList.add('hidden');
            container.innerHTML = session.accounts.map((acc) => {
                const abone = acc.aboneNo || acc.uyeNo || acc.sicilNo || acc.gensicilNo;
                const key = acc.accountKey || (acc.gensicilNo + '|' + abone);
                const details = Array.isArray(acc.details) && acc.details.length
                    ? `<p class="text-kiosk-xs text-municipalGray-500 mt-1">${acc.details.join(' · ')}</p>`
                    : '';
                const debtAmount = formatCurrency(Number(acc.totalDebt) || 0);
                const debtBlock = `
                    <div class="shrink-0 text-right pl-2">
                        <p class="text-kiosk-xs text-municipalGray-500">Toplam borç</p>
                        <p class="text-kiosk-lg font-bold text-municipal-700 mt-0.5 tabular-nums">${debtAmount}</p>
                    </div>`;
                return `
                <button type="button" class="account-card touch-btn w-full text-left bg-white border-2 border-municipal-200 hover:border-municipal-500 rounded-2xl px-5 py-4 shadow-sm"
                    data-account-key="${key}" role="listitem">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-kiosk-base font-bold text-municipalGray-800">${acc.fullName || 'Abonelik'}</p>
                            <p class="text-kiosk-sm text-municipal-700 font-semibold mt-1">Abone No: ${abone}</p>
                            <p class="text-kiosk-xs text-municipalGray-500 mt-0.5">Sicil: ${acc.sicilNo || acc.gensicilNo}</p>
                            <p class="text-kiosk-sm text-municipalGray-600 mt-2 leading-snug">${acc.address || 'Adres bilgisi kayıtta yok'}</p>
                            ${details}
                        </div>
                        ${debtBlock}
                    </div>
                </button>`;
            }).join('');

            container.querySelectorAll('.account-card').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const key = btn.dataset.accountKey;
                    const account = session.accounts.find(a => String(a.accountKey || (a.gensicilNo + '|' + a.aboneNo)) === String(key));
                    if (!account || !session.citizen) return;
                    errEl.classList.add('hidden');
                    btn.disabled = true;
                    onUserActivity();
                    try {
                        session.citizen = {
                            ...session.citizen,
                            gensicilNo: account.gensicilNo,
                            sicilNo: account.sicilNo || account.gensicilNo,
                            aboneNo: account.aboneNo || '',
                            totalDebt: account.totalDebt,
                            fullName: account.fullName || session.citizen.fullName,
                            address: account.address || '',
                            adi: account.adi || session.citizen.adi,
                            soyadi: account.soyadi || session.citizen.soyadi,
                            accountKey: account.accountKey || key,
                            needsSelection: false,
                        };
                        await loadDebtsForCitizen(session.citizen);
                    } catch (err) {
                        errEl.textContent = err.message;
                        errEl.classList.remove('hidden');
                        btn.disabled = false;
                    }
                });
            });
        }

        async function loadDebtsForCitizen(citizen) {
            const identityNo = citizen.identityNo;
            const gensicilNo = citizen.gensicilNo || '';
            const aboneNo = citizen.aboneNo || '';
            const { debts } = await fetchDebts(identityNo, 'tc', gensicilNo || undefined, aboneNo || undefined);
            let list = Array.isArray(debts) ? debts : [];

            // API boş dönerse karttaki abone toplam borcunu listeye düşür
            if (!list.length && citizen.totalDebt !== null && citizen.totalDebt !== undefined
                && Number(citizen.totalDebt) > 0) {
                list = [{
                    id: 'abone-' + (aboneNo || gensicilNo || identityNo),
                    type: 'Su aboneliği borcu',
                    period: aboneNo ? ('Abone No: ' + aboneNo) : '',
                    amount: Number(citizen.totalDebt),
                    dueDate: null,
                }];
            }

            session.debts = list;
            session.selectedIds.clear();
            renderDebtList();
            const tag = [
                'T.C.: ' + maskTc(identityNo),
                citizen.aboneNo ? ('Abone: ' + citizen.aboneNo) : null,
                citizen.sicilNo ? ('Sicil: ' + citizen.sicilNo) : null,
            ].filter(Boolean).join(' · ');
            document.getElementById('citizen-name').textContent =
                subscriberDisplayName(citizen) + ' — ' + tag;
            showScreen('debts');
        }

        document.getElementById('btn-back-accounts').addEventListener('click', () => {
            session.accounts = [];
            session.citizen = null;
            showScreen('login');
            onUserActivity();
        });

        document.getElementById('btn-back-login').addEventListener('click', () => {
            session.debts = [];
            session.selectedIds.clear();
            if (session.accounts && session.accounts.length > 1) {
                showScreen('accounts');
            } else {
                showScreen('login');
            }
            onUserActivity();
        });

        function groupDebtsForDisplay(debts) {
            const groups = [];
            const index = new Map();
            debts.forEach((debt) => {
                const meta = debt.meta || {};
                const key = meta.groupKey || debt.id;
                if (!index.has(key)) {
                    const group = {
                        key,
                        title: meta.groupTitle || debt.type || 'Borç',
                        period: debt.period || '',
                        dueDate: debt.dueDate || null,
                        aboneNo: meta.aboneNo || '',
                        modulBilgisi: meta.modulBilgisi || '',
                        amount: 0,
                        items: [],
                    };
                    index.set(key, group);
                    groups.push(group);
                }
                const group = index.get(key);
                group.items.push(debt);
                group.amount += Number(debt.amount) || 0;
                if (!group.dueDate && debt.dueDate) group.dueDate = debt.dueDate;
                if (!group.period && debt.period) group.period = debt.period;
            });
            return groups;
        }

        function renderDebtList() {
            const container = document.getElementById('debt-list');
            if (!session.debts.length) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center text-center px-6 py-10 bg-white border-2 border-municipalGray-400/20 rounded-2xl">
                        <p class="text-kiosk-base font-bold text-municipalGray-800">Sicil kaydınız bulundu ancak ödenecek borç bulunamadı.</p>
                        <p class="text-kiosk-xs text-municipalGray-500 mt-2">Bu abonelik için şu an ödenecek borcunuz görünmüyor.</p>
                    </div>
                `;
                updatePaymentPanel();
                return;
            }

            const groups = groupDebtsForDisplay(session.debts);
            container.innerHTML = groups.map((group) => {
                const ids = group.items.map(i => i.id);
                const allSelected = ids.every(id => session.selectedIds.has(id));
                const periodLine = [
                    group.aboneNo ? ('Abone No: ' + group.aboneNo) : null,
                    group.period || null,
                    group.dueDate ? formatDate(group.dueDate) : null,
                ].filter(Boolean).join(' · ');
                const modulLine = group.modulBilgisi
                    ? `<p class="text-kiosk-xs text-municipalGray-500 mt-0.5 truncate">${group.modulBilgisi}</p>`
                    : '';
                const breakdown = group.items.map(item => `
                    <div class="flex items-center justify-between gap-3 py-1.5 border-t border-municipalGray-200/80 first:border-t-0">
                        <p class="text-kiosk-xs text-municipalGray-700 min-w-0 leading-snug">${item.type || 'Kalem'}</p>
                        <p class="text-kiosk-xs font-semibold text-municipalGray-800 tabular-nums shrink-0">${formatCurrency(item.amount)}</p>
                    </div>
                `).join('');

                return `
                <div class="debt-group bg-white border-2 ${allSelected ? 'border-municipal-500' : 'border-municipalGray-400/30'} rounded-2xl overflow-hidden shadow-sm" data-group-key="${group.key}" role="listitem">
                    <label class="block cursor-pointer">
                        <input type="checkbox" class="debt-group-checkbox sr-only" data-ids="${ids.join(',')}" ${allSelected ? 'checked' : ''} />
                        <div class="debt-card-inner flex items-start gap-3 px-4 py-3 hover:bg-municipal-50/40 transition-all">
                            <div class="w-9 h-9 mt-0.5 rounded-lg border-2 ${allSelected ? 'border-municipal-500 bg-municipal-100' : 'border-municipal-300'} flex items-center justify-center shrink-0 checkbox-visual">
                                <svg class="w-5 h-5 text-municipal-600 ${allSelected ? '' : 'hidden'} check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-kiosk-sm font-bold text-municipalGray-800 leading-tight">${group.title}</p>
                                ${periodLine ? `<p class="text-kiosk-xs text-municipalGray-500 mt-0.5 truncate">${periodLine}</p>` : ''}
                                ${modulLine}
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-kiosk-base font-bold text-municipal-700 tabular-nums">${formatCurrency(group.amount)}</p>
                                <p class="text-[0.7rem] text-municipalGray-400 mt-0.5">${group.items.length} kalem</p>
                            </div>
                        </div>
                    </label>
                    <div class="px-4 pb-3 pl-16">
                        <div class="rounded-xl bg-municipalGray-50/80 px-3 py-1">
                            ${breakdown}
                        </div>
                    </div>
                </div>`;
            }).join('');

            container.querySelectorAll('.debt-group-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    const ids = String(cb.dataset.ids || '').split(',').filter(Boolean);
                    const groupEl = cb.closest('.debt-group');
                    const visual = groupEl.querySelector('.checkbox-visual');
                    const icon = groupEl.querySelector('.check-icon');
                    if (cb.checked) {
                        ids.forEach(id => session.selectedIds.add(id));
                        icon.classList.remove('hidden');
                        visual.classList.add('bg-municipal-100', 'border-municipal-500');
                        visual.classList.remove('border-municipal-300');
                        groupEl.classList.add('border-municipal-500');
                        groupEl.classList.remove('border-municipalGray-400/30');
                    } else {
                        ids.forEach(id => session.selectedIds.delete(id));
                        icon.classList.add('hidden');
                        visual.classList.add('border-municipal-300');
                        visual.classList.remove('bg-municipal-100', 'border-municipal-500');
                        groupEl.classList.remove('border-municipal-500');
                        groupEl.classList.add('border-municipalGray-400/30');
                    }
                    updatePaymentPanel();
                    onUserActivity();
                });
            });
            updatePaymentPanel();
        }

        function updatePaymentPanel() {
            const selected = session.debts.filter(d => session.selectedIds.has(d.id));
            const total = selected.reduce((s, d) => s + d.amount, 0);
            const groupCount = groupDebtsForDisplay(selected).length;
            const totalEl = document.getElementById('selected-total');
            const countEl = document.getElementById('selected-count');
            const payBtn = document.getElementById('btn-pay-bank');
            const errEl = document.getElementById('payment-error');
            if (totalEl) totalEl.textContent = formatCurrency(total);
            if (countEl) {
                countEl.textContent = selected.length
                    ? (groupCount + ' grup · ' + selected.length + ' kalem seçildi')
                    : '0 borç seçildi';
            }
            if (payBtn) payBtn.disabled = true;
            if (errEl) errEl.classList.add('hidden');
        }

        function openBankModal(total) {
            document.getElementById('bank-modal-total').textContent = formatCurrency(total);
            document.getElementById('bank-modal-error').classList.add('hidden');
            document.getElementById('bank-modal-loading').classList.add('hidden');
            document.getElementById('btn-confirm-bank').disabled = false;
            const modal = document.getElementById('bank-payment-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeBankModal() {
            const modal = document.getElementById('bank-payment-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.getElementById('btn-select-all').addEventListener('click', () => {
            const allChecked = session.selectedIds.size === session.debts.length && session.debts.length > 0;
            document.querySelectorAll('.debt-group-checkbox').forEach(cb => {
                cb.checked = !allChecked;
                cb.dispatchEvent(new Event('change'));
            });
            document.getElementById('btn-select-all').textContent = allChecked ? 'TÜMÜNÜ SEÇ' : 'SEÇİMİ KALDIR';
            onUserActivity();
        });

        document.getElementById('btn-pay-bank')?.addEventListener('click', async () => {
            // Banka POS ödemesi geçici olarak kapalı.
            return;
        });

        document.getElementById('btn-cancel-bank').addEventListener('click', () => {
            closeBankModal();
            document.getElementById('btn-pay-bank').disabled = session.selectedIds.size === 0;
            session.pendingPayment = null;
            water.pendingPayment = null;
            onUserActivity();
        });

        document.getElementById('btn-confirm-bank').addEventListener('click', async () => {
            const pending = session.pendingPayment || water.pendingPayment;
            if (!pending) {
                // session.pendingPayment burada boşsa, muhtemelen hareketsizlik zaman
                // aşımı modalı kapatıp oturumu sıfırladı — kullanıcıya sessizce hiçbir
                // şey olmamış gibi görünmesin, ne olduğunu açıkça bildir.
                document.getElementById('bank-modal-error').textContent =
                    'Oturum zaman aşımına uğradı. Lütfen borcunuzu tekrar seçip ödemeyi baştan başlatın.';
                document.getElementById('bank-modal-error').classList.remove('hidden');
                return;
            }
            const btnConfirm = document.getElementById('btn-confirm-bank');
            btnConfirm.disabled = true;
            paymentInFlight = true;
            document.getElementById('bank-modal-loading').classList.remove('hidden');
            document.getElementById('bank-modal-error').classList.add('hidden');
            onUserActivity();
            try {
                if (water.pendingPayment?.mode === 'invoice') {
                    const { transactionId, invoiceIds } = water.pendingPayment;
                    const confirmation = await waterConfirmInvoicePayment(transactionId, water.vendor, water.subscriber.aboneNo, invoiceIds);
                    closeBankModal();
                    showWaterSuccess('Su Faturası Ödendi', confirmation.message + ' Makbuz: ' + confirmation.receiptNo);
                } else if (water.pendingPayment?.mode === 'kontor') {
                    const { transactionId, tons } = water.pendingPayment;
                    const confirmation = await waterConfirmKontor(transactionId, water.vendor, water.subscriber.aboneNo, tons);
                    closeBankModal();
                    showWaterSuccess('Kontör Yüklendi', confirmation.message + ' Makbuz: ' + confirmation.receiptNo);
                } else if (session.pendingPayment) {
                    const { transactionId, debtIds } = session.pendingPayment;
                    const confirmation = await confirmPayment(
                        transactionId,
                        session.citizen.identityNo,
                        debtIds,
                        'tc',
                        session.citizen.gensicilNo || undefined,
                        session.citizen.aboneNo || undefined,
                    );
                    if (confirmation.status === 'completed') {
                        closeBankModal();
                        const receipt = confirmation.receipt || {};
                        const receiptLine = confirmation.receiptNo
                            ? 'Makbuz: ' + confirmation.receiptNo
                            : 'Makbuzunuz yazdırılıyor...';
                        const amountLine = receipt.toplamTutarYazi
                            ? receipt.toplamTutarYazi
                            : (receipt.toplamTutar ? receipt.toplamTutar + ' TL' : '');
                        document.getElementById('success-title').textContent = 'Ödemeniz Başarıyla Alınmıştır';
                        document.getElementById('success-message').textContent = [receiptLine, amountLine].filter(Boolean).join('\n');
                        showSuccessScreen();
                    } else {
                        // Beklenmeyen durum: hata fırlatılmadı ama status 'completed' değil.
                        // Sessizce hiçbir şey olmamış gibi bırakma — kullanıcıya bildir.
                        document.getElementById('bank-modal-error').textContent =
                            'Ödeme durumu doğrulanamadı (' + (confirmation.status || 'bilinmiyor') + '). Lütfen tekrar deneyin veya görevliye bildirin.';
                        document.getElementById('bank-modal-error').classList.remove('hidden');
                    }
                }
            } catch (err) {
                document.getElementById('bank-modal-error').textContent = err.message;
                document.getElementById('bank-modal-error').classList.remove('hidden');
            } finally {
                document.getElementById('bank-modal-loading').classList.add('hidden');
                btnConfirm.disabled = false;
                paymentInFlight = false;
                onUserActivity();
            }
        });

        function showSuccessScreen() {
            showScreen('success');
            let remaining = 7;
            const el = document.getElementById('success-countdown');
            successCountdownIv = setInterval(() => {
                remaining--;
                if (remaining > 0) el.textContent = remaining + ' saniye içinde ana ekrana dönülecek';
            }, 1000);
            successTimer = setTimeout(() => { clearInterval(successCountdownIv); goHome(); }, SUCCESS_REDIRECT_MS);
        }

        document.getElementById('btn-continue-session').addEventListener('click', () => { closeInactivityModal(); resetInactivityTimer(); });

        document.addEventListener('contextmenu', e => {
            if (isCopyableElement(e.target)) return;
            e.preventDefault();
        });
        document.addEventListener('selectstart', e => {
            if (isCopyableElement(e.target)) return;
            e.preventDefault();
        });
        document.addEventListener('copy', e => {
            if (selectionIsCopyable() || isCopyableElement(e.target)) return;
            e.preventDefault();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || e.key === 'F12') {
                e.preventDefault();
                return;
            }
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            if (selectionIsCopyable()) return;

            if (session.currentScreen === 'login' && handleLoginKeyboard(e)) return;
            if (session.currentScreen === 'waterCard' && handleWaterAboneKeyboard(e)) return;
        });
        ['touchstart', 'touchmove', 'mousedown', 'mousemove', 'click'].forEach(evt => {
            document.addEventListener(evt, onUserActivity, { passive: true });
        });

        renderIdentityDisplay();
        renderBirthDisplay();
        renderWaterAboneDisplay();
        showScreen('welcome');

        document.getElementById('btn-offline-retry').addEventListener('click', () => {
            checkSystemHealth();
        });
        document.getElementById('btn-offline-dismiss').addEventListener('click', () => {
            hideOfflineOverlay();
        });
        document.getElementById('btn-health-banner-close').addEventListener('click', () => {
            hideHealthBanner();
        });
        checkSystemHealth();
        setInterval(checkSystemHealth, 60000);

        // —— Gece bakımı (bilgisayar yerel saati) ——
        const maintOverlay = document.getElementById('maintenance-overlay');
        const maintBadge = document.getElementById('maint-badge');
        const maintTitle = document.getElementById('maint-title');
        const maintMsg = document.getElementById('maint-msg');
        const maintClock = document.getElementById('maint-clock');
        const maintUntil = document.getElementById('maint-until');
        let maintPhase = null;

        function pad2(n) { return String(n).padStart(2, '0'); }

        function minutesOfDay(d) {
            return d.getHours() * 60 + d.getMinutes();
        }

        function toMinutes(h, m) {
            return h * 60 + m;
        }

        function formatClock(d) {
            return pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
        }

        function formatHm(h, m) {
            return pad2(h) + ':' + pad2(m);
        }

        /** overnight: warn → end (e.g. 00:44–07:00) */
        function getMaintenancePhase(now) {
            const cur = minutesOfDay(now);
            const warn = toMinutes(MAINT.warnHour, MAINT.warnMinute);
            const start = toMinutes(MAINT.startHour, MAINT.startMinute);
            const end = toMinutes(MAINT.endHour, MAINT.endMinute);

            const inWindow = end > warn
                ? (cur >= warn && cur < end)
                : (cur >= warn || cur < end);

            if (!inWindow) return null;
            if (cur >= warn && cur < start) return 'warning';
            return 'active';
        }

        function applyMaintenanceUi(phase, now) {
            const endLabel = formatHm(MAINT.endHour, MAINT.endMinute);
            const startLabel = formatHm(MAINT.startHour, MAINT.startMinute);

            maintClock.textContent = formatClock(now);
            if (phase === 'warning') {
                maintBadge.textContent = 'Uyarı';
                maintTitle.textContent = 'Sistem bakıma giriyor';
                maintMsg.textContent = 'Saat ' + startLabel + '’te bakım başlayacak; sabah ' + endLabel + '’de yeniden hizmete açılacaktır.';
                maintUntil.textContent = '';
            } else {
                maintBadge.textContent = 'Sistem Bakımı';
                maintTitle.textContent = 'Sistem bakımdadır';
                maintMsg.textContent = 'Sabah ' + endLabel + '’de yeniden hizmete açılacaktır.';
                maintUntil.textContent = '';
            }
        }

        function tickMaintenance() {
            const now = new Date();
            const phase = getMaintenancePhase(now);

            if (phase) {
                applyMaintenanceUi(phase, now);
                if (!maintOverlay.classList.contains('is-visible')) {
                    maintOverlay.classList.add('is-visible');
                    try { resetSession(); showScreen('welcome'); } catch (_) { /* ignore */ }
                } else if (phase !== maintPhase) {
                    applyMaintenanceUi(phase, now);
                }
            } else if (maintOverlay.classList.contains('is-visible')) {
                maintOverlay.classList.remove('is-visible');
                try { goHome(); } catch (_) { /* ignore */ }
            }

            maintPhase = phase;
        }

        tickMaintenance();
        setInterval(tickMaintenance, 1000);
    })();
    </script>
</body>
</html>
