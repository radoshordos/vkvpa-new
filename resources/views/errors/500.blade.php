@extends('layouts.app')
@section('title', 'Chyba serveru – VKV PA')
@section('content')
  <h1>500 – Chyba serveru</h1>
  <p class="text-muted">Došlo k neočekávané chybě. Zkuste to prosím znovu za chvíli.</p>
  <p class="mt-3"><a href="{{ url('/') }}">← Zpět na úvod</a></p>
@endsection
