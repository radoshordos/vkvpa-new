{{--
    Admin nástroje vyhodnocení nad záznamem hlášení ($r = VkvpaData):
    1. řádek: P převzít · U upravit · X smazat (mazání potvrzuje modal
    z partials/del-modal – stránka ho musí includovat).
    2. řádek: EDI · EDIR · statistiky, má-li záznam nahraný deník;
    $bezEdi = true ho vynechá (stránka má pro EDI a statistiky vlastní sloupce).
--}}
@php
    $bezEdi = $bezEdi ?? false;
    // Vrátit převzetí (převzatý → nepřevzatý) lze jen do uzávěrky; po ní už jen upravit.
    $prijemOtevren = $prijemOtevren ?? true;
    $vratitBlokovano = $r->schvaleno && ! $prijemOtevren;
@endphp
<div class="mb-1 flex items-center gap-1">
    <form method="post" action="{{ route('zaznam.update', ['zaznam' => $r->id]) }}">
        @csrf
        @method('PATCH')
        <button type="submit" @disabled($vratitBlokovano)
                @class(['icon-btn', 'icon-btn-p', 'icon-btn-p-off' => ! $r->schvaleno, 'opacity-50 cursor-not-allowed' => $vratitBlokovano])
                title="{{ $vratitBlokovano ? __('app.act_takeover_blocked') : ($r->schvaleno ? __('app.act_untake') : __('app.act_take')) }}">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="2 8.5 6 12.5 14 3.5"/></svg>
        </button>
    </form>
    <a href="{{ route('hlaseni.index', ['id' => $r->id]) }}" class="icon-btn icon-btn-u" title="{{ __('app.act_edit') }}">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.5 2.5a1.5 1.5 0 0 1 2 2L5 13l-3 1 1-3 8.5-8.5z"/></svg>
    </a>
    <form method="post" action="{{ route('zaznam.destroy', ['zaznam' => $r->id]) }}">
        @csrf
        @method('DELETE')
        <button type="button" class="icon-btn icon-btn-x" title="{{ __('app.act_delete') }}"
                data-del-znacka="{{ $r->znacka }}">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="2" y1="4" x2="14" y2="4"/><path d="M5 4V2.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 .5.5V4M13 4v9a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 3 13V4"/></svg>
        </button>
    </form>
</div>
@if (! $bezEdi && $r->edihead_id)
    {{-- EDI · EDIR · statistiky – admin má vždy přístup --}}
    <div class="flex items-center gap-1">
        <x-edi-odkaz :head="$r->edihead_id" />
        <x-edi-odkaz :head="$r->edihead_id" reduced />
        <x-vizualizace-odkaz :head="$r->edihead_id" />
    </div>
@endif
