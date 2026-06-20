{{--
    Odznak výkonové kategorie (QRP/LP). Plný výkon se nezobrazuje.

    Použití:
      <x-vykon-badge :vykon="$r->vykon()" />

    Parametry:
      vykon – App\Enums\Vykon
    Další atributy (např. class) se prolnou na vnitřní <x-badge>.
--}}
@props(['vykon'])
@php($variant = $vykon?->badgeVariant())
@if ($variant)<x-badge :variant="$variant" {{ $attributes->class(['ml-1']) }}>{{ $vykon->label() }}</x-badge>@endif
