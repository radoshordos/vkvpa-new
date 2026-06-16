{{--
    SVG ikona z pojmenovaného registru. Anonymní Blade komponenta.
    Nahrazuje inline SVG kopírované po šablonách.

    Použití:
      <x-icon name="arrow-right" class="h-4 w-4" />
      <x-icon name="menu" class="h-5 w-5" />

    Parametry:
      name – klíč z registru níže (povinné)
    Třídy a další atributy se prolnou na <svg> (např. class="h-5 w-5").
--}}
@props(['name'])
@php
    // vb = viewBox, sw = stroke-width, p = obsah (cesty)
    $icons = [
        'menu'        => ['vb' => '0 0 24 24', 'sw' => 2,    'p' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>'],
        'close'       => ['vb' => '0 0 24 24', 'sw' => 2,    'p' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>'],
        'moon'        => ['vb' => '0 0 24 24', 'sw' => 2,    'p' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>'],
        'sun'         => ['vb' => '0 0 24 24', 'sw' => 2,    'p' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36l-1.42-1.42M7.05 7.05L5.64 5.64m12.72 0l-1.42 1.42M7.05 16.95l-1.41 1.41M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>'],
        'file'        => ['vb' => '0 0 16 16', 'sw' => 1.5,  'p' => '<path d="M9 2H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6L9 2Z"/><path d="M9 2v4h4M8 9.5v3M6.5 11l1.5-1.5 1.5 1.5"/>'],
        'pencil'      => ['vb' => '0 0 16 16', 'sw' => 1.5,  'p' => '<path d="M11.5 2.5a1.5 1.5 0 0 1 2 2L5 13l-3 1 1-3 8.5-8.5Z"/>'],
        'arrow-right'     => ['vb' => '0 0 16 16', 'sw' => 1.75, 'p' => '<path d="M3 8h10M9 4l4 4-4 4"/>'],
        'triangle-alert'  => ['vb' => '0 0 24 24', 'sw' => 2,    'p' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>'],
    ];
    $icon = $icons[$name] ?? null;
@endphp
@if ($icon)
    <svg {{ $attributes->merge(['fill' => 'none', 'stroke' => 'currentColor', 'aria-hidden' => 'true']) }}
         viewBox="{{ $icon['vb'] }}" stroke-width="{{ $icon['sw'] }}">{!! $icon['p'] !!}</svg>
@endif
