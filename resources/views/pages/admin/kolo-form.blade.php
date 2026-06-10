@extends('layouts.app')
@section('title', $kolo ? __('admin.kolo_edit_title') : __('admin.kolo_create_title'))
@section('content')

@php
    $sug = $suggested ?? [];
    // Předvyplnění: při vytváření z ContestCalendar, při editaci z modelu.
    $defNazev      = old('nazev',          $kolo?->nazev          ?? ($sug['nazev'] ?? ''));
    $defKonani     = old('datum_konani',   $kolo?->datum_konani?->format('Y-m-d') ?? ($sug['datum_konani'] ?? ''));
    $defUzaverky   = old('datum_uzaverky', $kolo?->datum_uzaverky?->format('Y-m-d\TH:i') ?? ($sug['datum_uzaverky'] ?? ''));
    $defPoznamka   = old('poznamka',       $kolo?->poznamka       ?? '');
    $defAktivni    = old('aktivni',        $kolo?->aktivni        ?? false);
@endphp

<h1>{{ $kolo ? __('admin.kolo_edit_heading') : __('admin.kolo_create_heading') }}</h1>

<x-form-errors />

<div class="card max-w-2xl p-5">
    <form method="post"
          action="{{ $kolo ? route('kola.admin.update', $kolo->id) : route('kola.admin.store') }}"
          class="space-y-4">
        @csrf
        @if ($kolo)
            @method('PATCH')
        @endif

        <x-field name="nazev" id="nazev" :label="__('admin.kolo_field_name')" required
                 :value="$defNazev" maxlength="250" />

        <div class="grid gap-x-5 sm:grid-cols-2">
            <x-field name="datum_konani" id="datum_konani" type="date" required
                     :label="__('admin.kolo_field_date')" :value="$defKonani" :hint="$kolo ? null : __('admin.kolo_hint_date')"
                     :readonly="$kolo !== null" />

            <x-field name="datum_uzaverky" id="datum_uzaverky" type="datetime-local" required
                     :label="__('admin.kolo_field_deadline')" :value="$defUzaverky" :hint="__('admin.kolo_hint_deadline')" />
        </div>

        <x-field name="poznamka" id="poznamka" :label="__('admin.kolo_field_note')"
                 :value="$defPoznamka" maxlength="250" />

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="aktivni" value="0">
            <input id="aktivni" name="aktivni" type="checkbox" value="1"
                   @checked($defAktivni)>
            {{ __('admin.kolo_field_active') }}
        </label>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('kola.admin.index') }}" class="btn btn-ghost">{{ __('admin.btn_cancel') }}</a>
            <button type="submit" class="btn btn-primary">
                {{ $kolo ? __('admin.kolo_btn_save') : __('admin.kolo_btn_create') }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script @cspNonce>
(function () {
    var datumKonani   = document.getElementById('datum_konani');
    var datumUzaverky = document.getElementById('datum_uzaverky');
    var nazev         = document.getElementById('nazev');

    if (! datumKonani) { return; }

    datumKonani.addEventListener('change', function () {
        var val = this.value; // YYYY-MM-DD
        if (! val) { return; }

        var parts = val.split('-');
        if (parts.length !== 3) { return; }

        // Datum závodu jako UTC půlnoc
        var d = new Date(Date.UTC(
            parseInt(parts[0], 10),
            parseInt(parts[1], 10) - 1,
            parseInt(parts[2], 10)
        ));
        if (isNaN(d.getTime())) { return; }

        // Uzávěrka = nejbližší následující pátek (dayOfWeek 5) 23:59
        var dayOfWeek = d.getUTCDay(); // 0 = Sun … 6 = Sat
        var daysUntilFriday = (5 - dayOfWeek + 7) % 7 || 7;
        var deadline = new Date(d);
        deadline.setUTCDate(deadline.getUTCDate() + daysUntilFriday);

        var yyyy = deadline.getUTCFullYear();
        var mm   = String(deadline.getUTCMonth() + 1).padStart(2, '0');
        var dd   = String(deadline.getUTCDate()).padStart(2, '0');
        datumUzaverky.value = yyyy + '-' + mm + '-' + dd + 'T23:59';

        // Název kola dle měsíce/roku data závodu
        var ym = String(d.getUTCMonth() + 1).padStart(2, '0');
        var yy = d.getUTCFullYear();
        nazev.value = ym + '/' + yy;
    });
}());
</script>
@endpush

@endsection
