{{-- Logo + T.C. Kırklareli Belediye Başkanlığı (+ isteğe bağlı ekran başlığı) --}}
@php
    $screenTitle = $screenTitle ?? null;
    $subtitle = $subtitle ?? null;
    $subtitleId = $subtitleId ?? null;
    $logoClass = $logoClass ?? 'h-12';
@endphp
<img src="{{ asset('images/logo.png') }}" alt="T.C. Kırklareli Belediyesi" class="{{ $logoClass }} w-auto shrink-0" />
<div class="min-w-0">
    <p class="text-kiosk-sm font-bold tracking-wide leading-tight truncate">T.C. Kırklareli Belediye Başkanlığı</p>
    @if ($screenTitle)
        <p class="text-kiosk-xs opacity-80 leading-tight mt-0.5 truncate">{{ $screenTitle }}</p>
    @endif
    @if ($subtitleId)
        <p id="{{ $subtitleId }}" class="text-kiosk-xs opacity-80 mt-0.5 truncate">{{ $subtitle }}</p>
    @elseif ($subtitle)
        <p class="text-kiosk-xs opacity-80 mt-0.5 truncate">{{ $subtitle }}</p>
    @endif
</div>
