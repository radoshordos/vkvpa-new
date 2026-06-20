{{--
    Strukturovaná data (JSON-LD) jednoho závodního kola jako schema.org/Event.
    Online akce → eventAttendanceMode + VirtualLocation; odkaz vede na veřejnou
    stránku diskuse kola (vždy existuje). Vkládá se uvnitř @section('jsonld').

    Očekává: $kolo (App\Models\VkvpaKola)
--}}
@php
    $event = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $kolo->nazev,
        'startDate' => $kolo->datum_konani?->toAtomString(),
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
        'location' => ['@type' => 'VirtualLocation', 'url' => url('/')],
        'organizer' => ['@type' => 'Organization', 'name' => __('app.site_title'), 'url' => url('/')],
        'url' => route('diskuse.show', $kolo->id),
    ], static fn ($v): bool => $v !== null);
@endphp
<script type="application/ld+json" @cspNonce>
  {!! json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
