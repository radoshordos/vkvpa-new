@props(['head', 'target' => null])

{{-- Odkaz na vizualizaci deníku (mapy + grafy). Nahrazuje dřívější textové
     odkazy na samostatné mapové pohledy M/N/S/C – zjednodušení UX. --}}
<a href="{{ route('edi.vizualizace', ['head' => $head]) }}"
   @if ($target) target="{{ $target }}" @endif
   {{ $attributes->merge(['class' => 'action-link inline-flex items-center']) }}
   title="Vizualizace deníku – mapy a grafy"
   aria-label="Vizualizace deníku">
    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
        <line x1="8" y1="2" x2="8" y2="18"/>
        <line x1="16" y1="6" x2="16" y2="22"/>
    </svg>
</a>
