{{--
    Odznak (badge). Anonymní Blade komponenta.

    Použití:
      <x-badge variant="ok">{{ __('…') }}</x-badge>
      <x-badge variant="brand" class="ml-1">{{ __('…') }} <b>{{ $n }}</b></x-badge>
      <x-badge>LP</x-badge>   {{-- bez varianty = neutrální --}}

    Parametry:
      variant – ok | warn | danger | brand | qrp | muted | skokan (volitelné)
    Třídy a další atributy se prolnou na <span> (např. class="ml-1").
--}}
@props(['variant' => null])
<span {{ $attributes->class(['badge', 'badge-' . $variant => $variant]) }}>{{ $slot }}</span>
