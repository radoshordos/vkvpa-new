@extends('layouts.app')
@section('title', 'Stránka nenalezena – VKV PA')
@section('content')
  <h1>404 – Stránka nenalezena</h1>
  <p class="text-muted">Požadovaná stránka neexistuje nebo byla přesunuta.</p>
  <p class="mt-3"><a href="{{ url('/') }}">← Zpět na úvod</a></p>
@endsection
