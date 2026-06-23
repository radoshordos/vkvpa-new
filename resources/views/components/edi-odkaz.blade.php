@props(['head', 'reduced' => false, 'target' => null])

{{-- Odkaz na EDI deník jako textový popisek „EDI" / „EDIR".
     reduced = redukovaný deník oříznutý na závodní okno 08–11 UTC. --}}
@php
    $route = $reduced ? 'edi.soubor.redukovany' : 'edi.soubor';
    $title = $reduced ? __('app.edi_link_reduced') : __('app.edi_link_original');
    $label = $reduced ? 'EDIR' : 'EDI';
@endphp

<x-result-link
    :href="route($route, ['head' => $head])"
    :title="$title"
    :target="$target"
    {{ $attributes }}
>{{ $label }}</x-result-link>
