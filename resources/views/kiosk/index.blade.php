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
            height: 712px;
        }
        .login-left {
            padding: 2rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1.25rem;
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
            border: 3px solid #1e5a9e;
            border-radius: 1rem;
            padding: 1.25rem 1rem;
            box-shadow: 0 4px 14px rgba(30, 90, 158, 0.12);
        }
        .digit-row {
            display: flex;
            justify-content: space-between;
            gap: 6px;
        }
        .digit-slot {
            flex: 1;
            min-width: 0;
            height: 80px;
            border: 2px solid #bfdbfe;
            border-radius: 0.75rem;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.125rem;
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
        .btn-query-wide {
            width: 100%;
            padding: 1.125rem 1rem;
            font-size: 1.25rem;
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
    </style>
</head>
<body oncontextmenu="return false;" ondragstart="return false;">

    {{-- EKRAN 1: KARŞILAMA --}}
    <section id="screen-welcome" class="kiosk-screen active flex-col items-center justify-center bg-gradient-to-b from-municipal-500 to-municipal-700 relative">
        <div class="absolute top-0 left-0 right-0 flex items-center justify-between px-8 py-5">
            <div class="flex items-center gap-4">
                <div class="w-20 h-14 rounded-md overflow-hidden shadow-xl border-2 border-white/30" aria-hidden="true">
                    <div class="w-full h-full relative bg-[#e30a17]">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-8 h-8 rounded-full bg-white relative">
                                <div class="absolute w-6 h-6 rounded-full bg-[#e30a17] top-1/2 left-1/2 -translate-x-1/3 -translate-y-1/2"></div>
                                <div class="absolute w-1.5 h-1.5 bg-white rounded-full top-[18%] left-[62%]"></div>
                                <div class="absolute w-1.5 h-1.5 bg-white rounded-full top-[38%] left-[72%]"></div>
                                <div class="absolute w-1.5 h-1.5 bg-white rounded-full top-[62%] left-[72%]"></div>
                                <div class="absolute w-1.5 h-1.5 bg-white rounded-full top-[78%] left-[58%]"></div>
                                <div class="absolute w-1.5 h-1.5 bg-white rounded-full top-[78%] left-[38%]"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-white">
                    <p class="text-kiosk-xs font-medium opacity-80">T.C.</p>
                    <p class="text-kiosk-base font-bold tracking-wide">Belediye Hizmetleri</p>
                </div>
            </div>
            <div class="w-16 h-16 rounded-full bg-white/15 border-2 border-white/40 flex items-center justify-center shadow-lg" aria-label="Belediye Logosu">
                <svg class="w-9 h-9 text-white/90" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3L2 12h3v8h6v-6h2v6h6v-8h3L12 3z"/></svg>
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
        <header class="bg-municipal-600 text-white px-8 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <button id="btn-menu-back" type="button" class="touch-btn w-11 h-11 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="text-kiosk-lg font-bold">Hizmet Seçimi</h2>
            </div>
        </header>
        <div class="flex-1 flex items-center justify-center px-10 gap-8">
            <button id="btn-menu-debt" type="button" class="touch-btn flex-1 max-w-md bg-white border-3 border-municipal-300 rounded-3xl p-10 shadow-xl hover:border-municipal-500 text-left">
                <div class="w-16 h-16 rounded-2xl bg-municipal-100 flex items-center justify-center mb-5">
                    <svg class="w-9 h-9 text-municipal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <h3 class="text-kiosk-xl font-bold text-municipalGray-800 mb-2">Borç Ödeme &amp; Sorgulama</h3>
                <p class="text-kiosk-sm text-municipalGray-600">Abone numaranızla belediye borçlarınızı görüntüleyin ve ödeyin.</p>
            </button>
            <button id="btn-menu-water" type="button" class="touch-btn flex-1 max-w-md bg-white border-3 border-cyan-400 rounded-3xl p-10 shadow-xl hover:border-cyan-600 text-left">
                <div class="w-16 h-16 rounded-2xl bg-cyan-100 flex items-center justify-center mb-5">
                    <svg class="w-9 h-9 text-cyan-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-4 4-6 7-6 10a6 6 0 1012 0c0-3-2-6-6-10z"/></svg>
                </div>
                <h3 class="text-kiosk-xl font-bold text-municipalGray-800 mb-2">Kartlı Su Sayacı</h3>
                <p class="text-kiosk-sm text-municipalGray-600">Baylan veya Metlab kartınızla fatura ödeyin, avans veya kontör yükleyin.</p>
            </button>
        </div>
    </section>

    {{-- EKRAN: SU — MARKA SEÇİMİ --}}
    <section id="screen-water-vendor" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-cyan-700 text-white px-8 py-4 flex items-center gap-3 shrink-0">
            <button id="btn-water-vendor-back" type="button" class="touch-btn w-11 h-11 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <h2 class="text-kiosk-lg font-bold">Sayaç Markası</h2>
        </header>
        <div class="flex-1 flex flex-col items-center justify-center px-10">
            <p class="text-kiosk-base text-municipalGray-600 mb-8 text-center">Abonelik kartınızın markasını seçiniz</p>
            <div class="flex gap-8 w-full max-w-3xl justify-center">
                <button type="button" data-vendor="baylan" class="vendor-card touch-btn flex-1 max-w-sm bg-white border-3 border-municipal-200 rounded-3xl p-8 text-center shadow-lg">
                    <div class="text-kiosk-2xl font-black text-municipal-700 mb-2">BAYLAN</div>
                    <p class="text-kiosk-sm text-municipalGray-500">Ön ödemeli NFC kart</p>
                    <p class="text-kiosk-xs text-municipalGray-400 mt-3">Test: 12345, 27126</p>
                </button>
                <button type="button" data-vendor="metlab" class="vendor-card touch-btn flex-1 max-w-sm bg-white border-3 border-municipal-200 rounded-3xl p-8 text-center shadow-lg">
                    <div class="text-kiosk-2xl font-black text-emerald-700 mb-2">METLAB</div>
                    <p class="text-kiosk-sm text-municipalGray-500">IC akıllı kart</p>
                    <p class="text-kiosk-xs text-municipalGray-400 mt-3">Test: 67890, 54321</p>
                </button>
            </div>
        </div>
    </section>

    {{-- EKRAN: SU — İŞLEM TÜRÜ --}}
    <section id="screen-water-action" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-cyan-700 text-white px-8 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <button id="btn-water-action-back" type="button" class="touch-btn w-11 h-11 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div>
                    <h2 class="text-kiosk-lg font-bold">İşlem Türü</h2>
                    <p id="water-vendor-label" class="text-kiosk-xs opacity-80"></p>
                </div>
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
        <header class="bg-cyan-700 text-white px-8 py-3 flex items-center justify-between shrink-0 h-14">
            <div class="flex items-center gap-3">
                <button id="btn-water-card-back" type="button" class="touch-btn w-10 h-10 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="text-kiosk-base font-bold">Kart Okuma</h2>
            </div>
            <span id="water-step-label" class="text-kiosk-xs opacity-75">Adım 1</span>
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
        <header class="bg-cyan-700 text-white px-8 py-4 flex items-center gap-3 shrink-0">
            <button id="btn-water-advance-back" type="button" class="touch-btn w-11 h-11 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <h2 class="text-kiosk-lg font-bold">Avans Kredi Yükleme</h2>
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
        <header class="bg-cyan-700 text-white px-8 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-water-invoices-back" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div class="min-w-0">
                    <h2 class="text-kiosk-lg font-bold">Su Faturaları</h2>
                    <p id="water-invoice-subscriber" class="text-kiosk-xs opacity-80 truncate"></p>
                </div>
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
        <header class="bg-cyan-700 text-white px-8 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-water-kontor-back" type="button" class="touch-btn w-11 h-11 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div class="min-w-0">
                    <h2 class="text-kiosk-lg font-bold">Kontör Yükleme</h2>
                    <p id="water-kontor-subscriber" class="text-kiosk-xs opacity-80 truncate"></p>
                </div>
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
        <header class="bg-municipal-600 text-white px-8 py-3 flex items-center justify-between shrink-0 h-14">
            <div class="flex items-center gap-3">
                <button id="btn-back-welcome" type="button" class="touch-btn w-10 h-10 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="text-kiosk-base font-bold">Abone Bilgileri</h2>
            </div>
            <span class="text-kiosk-xs opacity-75">Adım 1 / 2</span>
        </header>
        <div class="login-split">
            <div class="login-left">
                <div>
                    <h3 class="text-kiosk-xl font-bold text-municipalGray-800 mb-3">Borç Sorgulama</h3>
                    <p id="identity-hint" class="text-kiosk-sm text-municipalGray-600 leading-snug">
                        Abone numaranızı numaratör veya fiziksel klavye (NumLock) ile giriniz.
                    </p>
                </div>
                <div class="identity-strip">
                    <p class="text-kiosk-xs text-municipalGray-500 mb-2 font-medium uppercase tracking-wide">Abone No</p>
                    <div id="digit-row" class="abone-digit-row justify-start flex-wrap" aria-live="polite"></div>
                </div>
                <input id="input-identity" type="text" class="sr-only" maxlength="10" readonly aria-label="Abone numarası" />
                <p id="login-error" class="text-kiosk-sm text-red-600 font-medium hidden" role="alert"></p>
                <button id="btn-query" type="button" disabled
                    class="touch-btn btn-query-wide bg-municipal-600 text-white font-bold rounded-2xl shadow-xl hover:bg-municipal-700 disabled:opacity-40">
                    BORÇLARI SORGULA
                </button>
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

    {{-- EKRAN 3: BORÇ LİSTESİ --}}
    <section id="screen-debts" class="kiosk-screen flex-col bg-gray-50">
        <header class="bg-municipal-600 text-white px-8 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <button id="btn-back-login" type="button" class="touch-btn w-11 h-11 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div class="min-w-0">
                    <h2 class="text-kiosk-lg font-bold">Borç Listesi</h2>
                    <p id="citizen-name" class="text-kiosk-xs opacity-80 mt-0.5 truncate"></p>
                </div>
            </div>
            <span class="text-kiosk-xs opacity-75 shrink-0">Adım 2 / 2</span>
        </header>
        <div class="flex-1 flex overflow-hidden min-h-0">
            <div class="flex-1 px-6 py-4 overflow-hidden flex flex-col min-w-0">
                <div class="flex items-center justify-between mb-3 gap-2">
                    <p class="text-kiosk-sm text-municipalGray-600">Borçları seçiniz</p>
                    <button id="btn-select-all" type="button" class="touch-btn text-kiosk-xs font-semibold text-municipal-600 bg-municipal-50 px-4 py-2 rounded-xl border-2 border-municipal-200 shrink-0">TÜMÜNÜ SEÇ</button>
                </div>
                <div id="debt-list" class="flex-1 min-h-0 space-y-2" role="list"></div>
            </div>
            <aside class="w-[290px] shrink-0 bg-white border-l-2 border-municipal-200 flex flex-col items-center justify-center px-5 py-6 shadow-inner">
                <div class="text-center mb-5">
                    <p class="text-kiosk-xs text-municipalGray-500 mb-1">Seçilen Toplam</p>
                    <p id="selected-total" class="text-kiosk-xl font-bold text-municipal-700">0,00 ₺</p>
                    <p id="selected-count" class="text-kiosk-xs text-municipalGray-500 mt-1">0 borç seçildi</p>
                </div>
                <div class="w-28 h-20 mb-5 rounded-2xl bg-municipal-50 border-2 border-municipal-200 flex items-center justify-center">
                    <svg class="w-16 h-12 text-municipal-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 64 48">
                        <rect x="2" y="6" width="60" height="36" rx="4" stroke-width="2"/>
                        <rect x="2" y="14" width="60" height="8" fill="currentColor" opacity="0.15" stroke="none"/>
                        <rect x="8" y="30" width="20" height="4" rx="1" fill="currentColor" opacity="0.4" stroke="none"/>
                        <rect x="36" y="28" width="12" height="8" rx="2" stroke-width="2"/>
                    </svg>
                </div>
                <button id="btn-pay-bank" type="button" disabled
                    class="touch-btn w-full bg-municipal-600 text-white font-bold text-kiosk-sm px-4 py-5 rounded-2xl shadow-xl hover:bg-municipal-700 disabled:opacity-40 flex flex-col items-center gap-2">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    BANKA KARTI İLE ÖDE
                </button>
                <p class="text-[0.7rem] text-municipalGray-500 text-center mt-3 leading-snug">Kartınızı yanınızdaki POS cihazına okutunuz</p>
                <p id="payment-error" class="mt-3 text-kiosk-xs text-red-600 text-center hidden" role="alert"></p>
            </aside>
        </div>
    </section>

    {{-- EKRAN 4: BAŞARILI --}}
    <section id="screen-success" class="kiosk-screen flex-col items-center justify-center bg-gradient-to-b from-green-50 to-white">
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

    <script>
    (function () {
        'use strict';

        function resolveApiBase() {
            const path = window.location.pathname.replace(/\/kiosk\/?$/, '').replace(/\/?$/, '');
            return window.location.origin + path + '/api/kiosk';
        }

        const API_BASE = resolveApiBase();
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

        async function apiRequest(url, options = {}) {
            try {
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...options.headers },
                    credentials: 'same-origin',
                    ...options,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const detail = data.sonucKodu ? ` (${data.sonucKodu})` : '';
                    throw new Error((data.message || `Sunucu hatası (${res.status})`) + detail);
                }
                return data;
            } catch (err) {
                if (err instanceof TypeError) {
                    throw new Error('API bağlantısı kurulamadı. Adres: ' + url);
                }
                throw err;
            }
        }

        async function fetchCitizen(accountNo) {
            return apiRequest(`${API_BASE}/citizen/${accountNo}`);
        }

        async function fetchDebts(accountNo) {
            return apiRequest(`${API_BASE}/debts/${accountNo}`);
        }

        async function initiateBankPayment(identityNo, selectedDebtIds) {
            return apiRequest(`${API_BASE}/payment/bank`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ identityNo, debtIds: selectedDebtIds }),
            });
        }

        async function confirmPayment(transactionId, identityNo, debtIds) {
            return apiRequest(`${API_BASE}/payment/${transactionId}/confirm`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ identityNo, debtIds }),
            });
        }

        function subscriberDisplayName(s) {
            if (!s) return 'Abone';
            return s.fullName || [s.adi, s.soyadi].filter(Boolean).join(' ').trim() || ('Abone ' + (s.aboneNo || ''));
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
            return amount.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
        }

        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' });
        }

        const SCREENS = {
            welcome:       document.getElementById('screen-welcome'),
            menu:          document.getElementById('screen-menu'),
            login:         document.getElementById('screen-login'),
            debts:         document.getElementById('screen-debts'),
            success:       document.getElementById('screen-success'),
            waterVendor:   document.getElementById('screen-water-vendor'),
            waterAction:   document.getElementById('screen-water-action'),
            waterCard:     document.getElementById('screen-water-card'),
            waterAdvance:  document.getElementById('screen-water-advance'),
            waterInvoices: document.getElementById('screen-water-invoices'),
            waterKontor:   document.getElementById('screen-water-kontor'),
        };

        const KONTOR_OPTIONS = [5, 10, 20, 30, 40, 50];

        const session = { citizen: null, debts: [], selectedIds: new Set(), currentScreen: 'welcome', pendingPayment: null };
        const water = {
            vendor: null, action: null, subscriber: null,
            invoices: [], selectedInvoiceIds: new Set(),
            kontorIndex: 0, kontorAmount: 0, pendingPayment: null,
        };
        const INACTIVITY_MS = 45000, WARNING_COUNTDOWN_S = 15, SUCCESS_REDIRECT_MS = 7000;
        let inactivityTimer, warningInterval, successTimer, successCountdownIv;

        function showScreen(name) {
            Object.values(SCREENS).forEach(el => el.classList.remove('active'));
            SCREENS[name].classList.add('active');
            session.currentScreen = name;
            resetInactivityTimer();
            if (name === 'login') renderIdentityDisplay();
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
        }

        function resetSession() {
            session.citizen = null; session.debts = []; session.selectedIds.clear();
            setIdentityValue('');
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
                if (inputIdentity.value.length < MAX_ACCOUNT_DIGITS) {
                    setIdentityValue(inputIdentity.value + digit);
                }
                loginError.classList.add('hidden');
                onUserActivity();
                return true;
            }
            if (e.key === 'Backspace') {
                e.preventDefault();
                setIdentityValue(inputIdentity.value.slice(0, -1));
                loginError.classList.add('hidden');
                onUserActivity();
                return true;
            }
            if (e.key === 'Delete' || e.key === 'Escape') {
                e.preventDefault();
                setIdentityValue('');
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
            if (session.currentScreen === 'welcome') return;
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
        const digitRow = document.getElementById('digit-row');
        const btnQuery = document.getElementById('btn-query');
        const loginError = document.getElementById('login-error');
        const MAX_ACCOUNT_DIGITS = 10;
        const MIN_ACCOUNT_DIGITS = 1;

        function renderIdentityDisplay() {
            const val = inputIdentity.value;
            let html = '';
            const slotCount = Math.max(val.length + 1, MIN_ACCOUNT_DIGITS);
            for (let i = 0; i < slotCount && i < MAX_ACCOUNT_DIGITS; i++) {
                const ch = val[i] || '';
                const cls = ['abone-digit'];
                if (ch) cls.push('filled');
                if (i === val.length && val.length < MAX_ACCOUNT_DIGITS) cls.push('active');
                html += `<div class="${cls.join(' ')}" aria-hidden="true">${ch}</div>`;
            }
            digitRow.innerHTML = html;
        }

        function updateQueryButton() {
            const len = inputIdentity.value.trim().length;
            btnQuery.disabled = len < MIN_ACCOUNT_DIGITS || len > MAX_ACCOUNT_DIGITS;
        }

        function setIdentityValue(val) {
            inputIdentity.value = val.replace(/\D/g, '').slice(0, MAX_ACCOUNT_DIGITS);
            renderIdentityDisplay();
            updateQueryButton();
        }

        document.querySelectorAll('.numpad-key').forEach(key => {
            key.addEventListener('click', () => {
                const action = key.dataset.key;
                let val = inputIdentity.value;
                const maxDigits = MAX_ACCOUNT_DIGITS;
                if (action === 'clear') val = '';
                else if (action === 'backspace') val = val.slice(0, -1);
                else if (val.length < maxDigits) val += action;
                setIdentityValue(val);
                loginError.classList.add('hidden');
                onUserActivity();
            });
        });

        document.getElementById('btn-start').addEventListener('click', () => { showScreen('menu'); onUserActivity(); });
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
                document.getElementById('water-vendor-label').textContent = vendorLabel(water.vendor) + ' kartlı sayaç';
                setTimeout(() => showScreen('waterAction'), 200);
                onUserActivity();
            });
        });

        document.getElementById('btn-water-vendor-back').addEventListener('click', () => showScreen('menu'));
        document.getElementById('btn-water-action-back').addEventListener('click', () => showScreen('waterVendor'));

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
            if (identityNo.length < MIN_ACCOUNT_DIGITS || identityNo.length > MAX_ACCOUNT_DIGITS) return;
            btnQuery.disabled = true;
            document.getElementById('login-loading').classList.remove('hidden');
            loginError.classList.add('hidden');
            onUserActivity();
            try {
                const citizen = await fetchCitizen(identityNo);
                const { debts } = await fetchDebts(identityNo);
                session.citizen = citizen;
                session.debts = debts;
                session.selectedIds.clear();
                renderDebtList();
                document.getElementById('citizen-name').textContent =
                    subscriberDisplayName(citizen) + ' — Abone ' + (citizen.aboneNo || identityNo);
                showScreen('debts');
            } catch (err) {
                loginError.textContent = err.message;
                loginError.classList.remove('hidden');
                updateQueryButton();
            } finally {
                document.getElementById('login-loading').classList.add('hidden');
            }
        });

        document.getElementById('btn-back-login').addEventListener('click', () => {
            session.debts = []; session.selectedIds.clear(); showScreen('login');
        });

        function renderDebtList() {
            const container = document.getElementById('debt-list');
            container.innerHTML = session.debts.map(debt => `
                <label class="block cursor-pointer" role="listitem">
                    <input type="checkbox" class="debt-checkbox sr-only" data-id="${debt.id}" />
                    <div class="debt-card-inner flex items-center gap-3 bg-white border-2 border-municipalGray-400/30 rounded-2xl px-4 py-3 shadow-sm hover:border-municipal-300 transition-all">
                        <div class="w-9 h-9 rounded-lg border-2 border-municipal-300 flex items-center justify-center shrink-0 checkbox-visual">
                            <svg class="w-5 h-5 text-municipal-600 hidden check-icon" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-kiosk-sm font-bold text-municipalGray-800 debt-type-clamp leading-tight">${debt.type}</p>
                            <p class="text-kiosk-xs text-municipalGray-500 mt-0.5 truncate">${debt.period} · ${formatDate(debt.dueDate)}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-kiosk-base font-bold text-municipal-700">${formatCurrency(debt.amount)}</p>
                            <p class="text-[0.7rem] text-municipalGray-400 mt-0.5">${debt.id}</p>
                        </div>
                    </div>
                </label>
            `).join('');

            container.querySelectorAll('.debt-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    const label = cb.closest('label');
                    if (cb.checked) {
                        session.selectedIds.add(cb.dataset.id);
                        label.querySelector('.check-icon').classList.remove('hidden');
                        label.querySelector('.checkbox-visual').classList.add('bg-municipal-100', 'border-municipal-500');
                    } else {
                        session.selectedIds.delete(cb.dataset.id);
                        label.querySelector('.check-icon').classList.add('hidden');
                        label.querySelector('.checkbox-visual').classList.remove('bg-municipal-100', 'border-municipal-500');
                    }
                    updatePaymentPanel(); onUserActivity();
                });
            });
            updatePaymentPanel();
        }

        function updatePaymentPanel() {
            const selected = session.debts.filter(d => session.selectedIds.has(d.id));
            const total = selected.reduce((s, d) => s + d.amount, 0);
            document.getElementById('selected-total').textContent = formatCurrency(total);
            document.getElementById('selected-count').textContent = selected.length + ' borç seçildi';
            document.getElementById('btn-pay-bank').disabled = selected.length === 0;
            document.getElementById('payment-error').classList.add('hidden');
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
            const allChecked = session.selectedIds.size === session.debts.length;
            document.querySelectorAll('.debt-checkbox').forEach(cb => {
                cb.checked = !allChecked;
                cb.dispatchEvent(new Event('change'));
            });
            document.getElementById('btn-select-all').textContent = allChecked ? 'TÜMÜNÜ SEÇ' : 'SEÇİMİ KALDIR';
            onUserActivity();
        });

        document.getElementById('btn-pay-bank').addEventListener('click', async () => {
            const selectedIds = [...session.selectedIds];
            if (!selectedIds.length) return;
            const btnPay = document.getElementById('btn-pay-bank');
            const total = session.debts.filter(d => session.selectedIds.has(d.id)).reduce((s, d) => s + d.amount, 0);
            btnPay.disabled = true;
            onUserActivity();
            try {
                const payment = await initiateBankPayment(session.citizen.identityNo, selectedIds);
                session.pendingPayment = { transactionId: payment.transactionId, debtIds: selectedIds };
                openBankModal(total);
            } catch (err) {
                const errEl = document.getElementById('payment-error');
                errEl.textContent = err.message;
                errEl.classList.remove('hidden');
                btnPay.disabled = false;
            }
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
            if (!pending) return;
            const btnConfirm = document.getElementById('btn-confirm-bank');
            btnConfirm.disabled = true;
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
                    const confirmation = await confirmPayment(transactionId, session.citizen.identityNo, debtIds);
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
                    }
                }
            } catch (err) {
                document.getElementById('bank-modal-error').textContent = err.message;
                document.getElementById('bank-modal-error').classList.remove('hidden');
                btnConfirm.disabled = false;
            } finally {
                document.getElementById('bank-modal-loading').classList.add('hidden');
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
        renderWaterAboneDisplay();
        showScreen('welcome');
    })();
    </script>
</body>
</html>
