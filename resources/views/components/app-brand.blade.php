<a href="/" wire:navigate class="flex items-center gap-2.5 px-5 py-4" aria-label="{{ config('app.name') }} — {{ __('app.common.home') }}">
    <svg class="w-8 h-10 shrink-0" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <title>{{ config('app.name') }}</title>
        <defs>
            <linearGradient id="logo-f" x1="0" y1="0" x2="28" y2="34" gradientUnits="userSpaceOnUse">
                <stop stop-color="#7678ED"/>
                <stop offset="1" stop-color="#3D348B"/>
            </linearGradient>
        </defs>
        <path d="M2 3C2 1.34 3.34 0 5 0H23C24.66 0 26 1.34 26 3V29L23 27L20 29L17 27L14 29L11 27L8 29L5 27L2 29V3Z"
              fill="white" fill-opacity="0.95"/>
        <path d="M8 6H20V9.5H12.5V13.5H18.5V17H12.5V24H8V6Z" fill="#2D2A6E"/>
        <circle cx="22" cy="5.5" r="2.5" fill="#F7B801"/>
    </svg>
    <div class="flex flex-col">
        <span class="text-base font-bold text-white leading-tight">{{ config('app.name') }}</span>
        <span class="text-xs text-white/50 leading-tight">Fattura Elettronica</span>
    </div>
</a>
