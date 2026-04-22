<a href="/" wire:navigate>
    <!-- Hidden when collapsed — receipt-shaped "F" logotype (inverted for dark sidebar) -->
    <div {{ $attributes->class(["hidden-when-collapsed"]) }}>
        <svg class="w-9 h-11" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="logo-f" x1="0" y1="0" x2="28" y2="34" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#7678ED"/>
                    <stop offset="1" stop-color="#3D348B"/>
                </linearGradient>
            </defs>
            {{-- White receipt silhouette with zigzag bottom --}}
            <path d="M2 3C2 1.34 3.34 0 5 0H23C24.66 0 26 1.34 26 3V29L23 27L20 29L17 27L14 29L11 27L8 29L5 27L2 29V3Z"
                  fill="white" fill-opacity="0.95"/>
            {{-- Dark "F" inside --}}
            <path d="M8 6H20V9.5H12.5V13.5H18.5V17H12.5V24H8V6Z" fill="#2D2A6E"/>
            {{-- Amber accent dot --}}
            <circle cx="22" cy="5.5" r="2.5" fill="#F7B801"/>
        </svg>
    </div>

    <!-- Display when collapsed — same receipt mark, smaller -->
    <div class="display-when-collapsed hidden mx-5 mt-4 lg:mb-6 h-[28px]">
        <svg class="w-6 h-7" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="logo-f-sm" x1="0" y1="0" x2="28" y2="34" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#7678ED"/>
                    <stop offset="1" stop-color="#3D348B"/>
                </linearGradient>
            </defs>
            <path d="M2 3C2 1.34 3.34 0 5 0H23C24.66 0 26 1.34 26 3V29L23 27L20 29L17 27L14 29L11 27L8 29L5 27L2 29V3Z"
                  fill="white" fill-opacity="0.95"/>
            <path d="M8 6H20V9.5H12.5V13.5H18.5V17H12.5V24H8V6Z" fill="#2D2A6E"/>
            <circle cx="22" cy="5.5" r="2.5" fill="#F7B801"/>
        </svg>
    </div>
</a>
