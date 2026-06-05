{{--
    Ukázka layoutu. Controller předá $active pro zvýraznění položky menu.
    Použití: return view('pages.example', ['active' => 'edit_hlaseni']);
--}}
@extends('layouts.app')

@section('title', 'Odeslat deník – VKV PA')

@section('content')
    <h1>Odeslat deník</h1>
    <p class="text-muted">Sem přijde obsah konkrétní stránky (formulář, výpis, tabulka…).</p>
@endsection
