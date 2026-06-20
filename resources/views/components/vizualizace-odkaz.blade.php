@props(['head', 'target' => null])

{{-- Odkaz na statistiky deníku (mapy + grafy, route edi.vizualizace).
     Nahrazuje dřívější textové odkazy na samostatné mapové pohledy M/N/S/C. --}}
<a href="{{ route('edi.vizualizace', ['head' => $head]) }}"
   @if ($target) target="{{ $target }}" @endif
   {{ $attributes->merge(['class' => 'icon-btn icon-btn-ghost icon-btn-label']) }}
   title="{{ __('app.viz_link_title') }}"
   aria-label="{{ __('app.viz_link_aria') }}">
    {{-- Sloupcový graf --}}
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="6" y1="20" x2="6" y2="12"/>
        <line x1="12" y1="20" x2="12" y2="5"/>
        <line x1="18" y1="20" x2="18" y2="9"/>
    </svg>
    {{ __('app.viz_link_label') }}
</a>
