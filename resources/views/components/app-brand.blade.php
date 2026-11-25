<x-app-link href="/" class="flex items-center gap-2.5 px-5 py-4" aria-label="{{ config('app.name') }} — {{ __('app.common.home') }}">
    <svg class="w-8 h-10 shrink-0" viewBox="-1 -1 28 34" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <title>{{ config('app.name') }}</title>
        <path d="M2 3C2 1.34 3.34 0 5 0H21.5C22.88 0 24 1.12 24 2.5V26.6L21.8 28.8L19.6 26.6L17.4 28.8L15.2 26.6L13 28.8L10.8 26.6L8.6 28.8L6.4 26.6L4.2 28.8L2 26.6V3Z"
              fill="white" fill-opacity="0.95"/>
        <path d="M17.2 0H21.5C22.88 0 24 1.12 24 2.5V6.8L17.2 0Z" fill="#F7B801"/>
        <path d="M17.2 0L24 6.8H18.4C17.74 6.8 17.2 6.26 17.2 5.6V0Z" fill="#E5A800"/>
        <path d="M6.8 6.2H17.6V9.3H10.9V12.7H16.4V15.8H10.9V23.2H6.8V6.2Z" fill="#2D2A6E"/>
    </svg>
    <div class="flex flex-col">
        <span class="text-base font-bold text-white leading-tight">{{ config('app.name') }}</span>
        <span class="text-xs text-white/50 leading-tight">Fattura Elettronica</span>
    </div>
</x-app-link>
