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
        .debt-type-clamp { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        #debt-list { overflow-y:auto; scrollbar-width:none; }
        #debt-list::-webkit-scrollbar { display:none; }
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

    {{-- EKRAN 2: VATANDAŞ GİRİŞİ — sol: uzun numara alanı, sağ: numaratör --}}
    <section id="screen-login" class="kiosk-screen bg-gray-50">
        <header class="bg-municipal-600 text-white px-8 py-3 flex items-center justify-between shrink-0 h-14">
            <div class="flex items-center gap-3">
                <button id="btn-back-welcome" type="button" class="touch-btn w-10 h-10 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Geri">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="text-kiosk-base font-bold">Kimlik Bilgileri</h2>
            </div>
            <span class="text-kiosk-xs opacity-75">Adım 1 / 2</span>
        </header>
        <div class="login-split">
            <div class="login-left">
                <div>
                    <h3 class="text-kiosk-xl font-bold text-municipalGray-800 mb-2">T.C. Kimlik No / Sicil No</h3>
                    <p class="text-kiosk-sm text-municipalGray-600 leading-snug">
                        Sağdaki numaratör ile numaranızı giriniz. T.C. Kimlik No 11 hane, Sicil No en az 5 hanedir.
                    </p>
                </div>
                <div class="identity-strip">
                    <p class="text-kiosk-xs text-municipalGray-500 mb-2 font-medium uppercase tracking-wide">Girilen Numara</p>
                    <div id="digit-row" class="digit-row" aria-live="polite"></div>
                </div>
                <input id="input-identity" type="text" class="sr-only" maxlength="11" readonly aria-label="T.C. Kimlik No veya Sicil No" />
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
        <h2 class="text-kiosk-xl font-bold text-green-700 mb-4 text-center">Ödemeniz Başarıyla Alınmıştır</h2>
        <p class="text-kiosk-base text-municipalGray-600 text-center">Makbuzunuz Yazdırılıyor...</p>
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
                if (!res.ok) throw new Error(data.message || `Sunucu hatası (${res.status})`);
                return data;
            } catch (err) {
                if (err instanceof TypeError) {
                    throw new Error('API bağlantısı kurulamadı. Adres: ' + url);
                }
                throw err;
            }
        }

        async function fetchCitizen(identityNo) {
            return apiRequest(`${API_BASE}/citizen/${identityNo}`);
        }

        async function fetchDebts(identityNo) {
            return apiRequest(`${API_BASE}/debts/${identityNo}`);
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

        function formatCurrency(amount) {
            return amount.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
        }

        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' });
        }

        const SCREENS = {
            welcome: document.getElementById('screen-welcome'),
            login:   document.getElementById('screen-login'),
            debts:   document.getElementById('screen-debts'),
            success: document.getElementById('screen-success'),
        };

        const session = { citizen: null, debts: [], selectedIds: new Set(), currentScreen: 'welcome', pendingPayment: null };
        const INACTIVITY_MS = 45000, WARNING_COUNTDOWN_S = 15, SUCCESS_REDIRECT_MS = 7000;
        let inactivityTimer, warningInterval, successTimer, successCountdownIv;

        function showScreen(name) {
            Object.values(SCREENS).forEach(el => el.classList.remove('active'));
            SCREENS[name].classList.add('active');
            session.currentScreen = name;
            resetInactivityTimer();
            if (name === 'login') renderIdentityDisplay();
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
            closeBankModal();
            document.getElementById('btn-select-all').textContent = 'TÜMÜNÜ SEÇ';
            clearTimeout(successTimer); clearInterval(successCountdownIv);
            closeInactivityModal();
            renderIdentityDisplay();
        }

        function goHome() { resetSession(); showScreen('welcome'); }

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
        const MAX_DIGITS = 11;

        function renderIdentityDisplay() {
            const val = inputIdentity.value;
            let html = '';
            for (let i = 0; i < MAX_DIGITS; i++) {
                const ch = val[i] || '';
                const cls = ['digit-slot'];
                if (ch) cls.push('filled');
                if (i === val.length && val.length < MAX_DIGITS) cls.push('active');
                html += `<div class="${cls.join(' ')}" aria-hidden="true">${ch}</div>`;
            }
            digitRow.innerHTML = html;
        }

        function updateQueryButton() { btnQuery.disabled = inputIdentity.value.trim().length < 5; }

        function setIdentityValue(val) {
            inputIdentity.value = val;
            renderIdentityDisplay();
            updateQueryButton();
        }

        document.querySelectorAll('.numpad-key').forEach(key => {
            key.addEventListener('click', () => {
                const action = key.dataset.key;
                let val = inputIdentity.value;
                if (action === 'clear') val = '';
                else if (action === 'backspace') val = val.slice(0, -1);
                else if (val.length < MAX_DIGITS) val += action;
                setIdentityValue(val);
                loginError.classList.add('hidden');
                onUserActivity();
            });
        });

        document.getElementById('btn-start').addEventListener('click', () => { showScreen('login'); onUserActivity(); });
        document.getElementById('btn-back-welcome').addEventListener('click', goHome);

        btnQuery.addEventListener('click', async () => {
            const identityNo = inputIdentity.value.trim();
            if (identityNo.length < 5) return;
            btnQuery.disabled = true;
            document.getElementById('login-loading').classList.remove('hidden');
            loginError.classList.add('hidden');
            onUserActivity();
            try {
                const citizen = await fetchCitizen(identityNo);
                const { debts } = await fetchDebts(identityNo);
                session.citizen = citizen; session.debts = debts; session.selectedIds.clear();
                renderDebtList();
                document.getElementById('citizen-name').textContent = citizen.fullName + ' — ' + identityNo;
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
            onUserActivity();
        });

        document.getElementById('btn-confirm-bank').addEventListener('click', async () => {
            if (!session.pendingPayment) return;
            const btnConfirm = document.getElementById('btn-confirm-bank');
            btnConfirm.disabled = true;
            document.getElementById('bank-modal-loading').classList.remove('hidden');
            document.getElementById('bank-modal-error').classList.add('hidden');
            onUserActivity();
            try {
                const { transactionId, debtIds } = session.pendingPayment;
                const confirmation = await confirmPayment(transactionId, session.citizen.identityNo, debtIds);
                if (confirmation.status === 'completed') {
                    closeBankModal();
                    showSuccessScreen();
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

        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('selectstart', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || e.key === 'F12') e.preventDefault();
        });
        ['touchstart', 'touchmove', 'mousedown', 'mousemove', 'click'].forEach(evt => {
            document.addEventListener(evt, onUserActivity, { passive: true });
        });

        renderIdentityDisplay();
        showScreen('welcome');
    })();
    </script>
</body>
</html>
