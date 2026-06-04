@extends('layouts.app')
@section('title', '{{ $nazev }} – Administrace VKV PA')
@section('content')
<h1>{{ $nazev }}</h1>
<p style="background:#fff8e1;border:1px solid #f0a500;padding:12px 16px;font-family:Arial,sans-serif;font-size:13px;color:#555;max-width:600px;">
    Tato sekce zatím není implementována.
    @if (! empty($popis))
        <br><br>{{ $popis }}
    @endif
</p>
@endsection
