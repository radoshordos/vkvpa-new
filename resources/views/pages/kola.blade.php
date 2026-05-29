@extends('layouts.app')
@section('title', 'Kola závodu – VKV PA')
@section('content')
<h1>Kola závodu / Contest periods</h1>

@if (session('announcement'))
  <p class="green">{{ session('announcement') }}</p>
@endif

@php $isAdmin = $isAdmin ?? (bool) (auth()->user()?->is_admin); @endphp

<table class="vypis">
  <tr><th>Datum konání</th><th>Uzávěrka</th><th>Název</th><th>Vyhodnoceno</th>@if ($isAdmin)<th>Akce</th>@endif</tr>
  @foreach ($kola as $k)
    <tr>
      <td>{{ $k->datum_konani?->format('d.m.Y') }}</td>
      <td>{{ $k->datum_uzaverky?->format('d.m.Y H:i') }}</td>
      <td>{{ $k->nazev }}</td>
      <td>{{ $k->vyhodnoceno?->format('d.m.Y H:i') ?? '—' }}</td>
      @if ($isAdmin)
        <td>
          @unless ($k->vyhodnoceno)
            <form action="{{ route('kolo.vyhodnotit', $k->id) }}" method="post" style="display:inline;">@csrf<button type="submit">vyhodnotit</button></form>
            <form action="{{ route('kolo.uzavrit', $k->id) }}" method="post" style="display:inline;">@csrf<button type="submit">uzavřít</button></form>
          @endunless
        </td>
      @endif
    </tr>
  @endforeach
</table>
@endsection
