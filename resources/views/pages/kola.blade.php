@extends('layouts.app')
@section('title', 'Kola závodu – VKV PA')
@section('content')
<h1>Kola závodu / Contest periods</h1>

@if (session('announcement'))
  <div class="alert alert-success">{{ session('announcement') }}</div>
@endif

@php $isAdmin = $isAdmin ?? (bool) (auth()->user()?->is_admin); @endphp

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Datum konání</th>
        <th>Uzávěrka</th>
        <th>Název</th>
        <th>Vyhodnoceno</th>
        @if ($isAdmin)<th>Akce</th>@endif
      </tr>
    </thead>
    <tbody>
      @foreach ($kola as $k)
        <tr>
          <td class="whitespace-nowrap">{{ $k->datum_konani?->format('d.m.Y') }}</td>
          <td class="whitespace-nowrap">{{ $k->datum_uzaverky?->format('d.m.Y H:i') }}</td>
          <td>{{ $k->nazev }}</td>
          <td class="whitespace-nowrap">{{ $k->vyhodnoceno?->format('d.m.Y H:i') ?? '—' }}</td>
          @if ($isAdmin)
            <td>
              @unless ($k->vyhodnoceno)
                <div class="flex flex-wrap gap-2">
                  <form action="{{ route('kola.vyhodnotit', $k->id) }}" method="post">@csrf<button type="submit" class="btn btn-primary btn-sm">vyhodnotit</button></form>
                  <form action="{{ route('kola.uzavrit', $k->id) }}" method="post">@csrf<button type="submit" class="btn btn-ghost btn-sm">uzavřít</button></form>
                </div>
              @endunless
            </td>
          @endif
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
