{{--
    Ukázka: takto se obsah jednotlivých legacy str=*.php stránek (Fáze 6)
    napojí na layout. Controller předá $active pro zvýraznění položky menu.

    Použití v controlleru:
        return view('pages.example', ['active' => 'edit_hlaseni']);
--}}
@extends('layouts.app')

@section('title', 'Odeslat deník – VKV PA')

@section('content')
    <h1>Odeslat deník</h1>
    <p>Sem přijde obsah konkrétní stránky (formulář, výpis, tabulka…).</p>
@endsection
