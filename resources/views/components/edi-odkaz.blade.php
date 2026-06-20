@props(['head', 'reduced' => false, 'target' => null])

{{-- Odkaz na EDI deník jako textový popisek „EDI" / „EDIR".
     reduced = redukovaný deník oříznutý na závodní okno 08–11 UTC. --}}
@php
    $route = $reduced ? 'edi.soubor.redukovany' : 'edi.soubor';
    $title = $reduced ? __('app.edi_link_reduced') : __('app.edi_link_original');
    $label = $reduced ? 'EDIR' : 'EDI';
@endphp

<a href="{{ route($route, ['head' => $head]) }}"
   @if ($target) target="{{ $target }}" @endif
   {{ $attributes->merge(['class' => 'icon-btn icon-btn-ghost icon-btn-label']) }}
   title="{{ $title }}"
   aria-label="{{ $title }}">{{ $label }}</a>
