@extends('layouts.app')
@section('title', $nazev . ' – Administrace VKV PA')
@section('content')
<h1>{{ $nazev }}</h1>
<div class="alert alert-info max-w-2xl">
    Tato sekce zatím není implementována.
    @if (! empty($popis))
        <br><br>{{ $popis }}
    @endif
</div>
@endsection
