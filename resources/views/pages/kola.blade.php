@extends('layouts.app')
@section('title', __('pages.kola.title'))
@section('content')
<h1>{{ __('pages.kola.heading') }}</h1>

@if (session('announcement'))
  <div class="alert alert-success">{{ session('announcement') }}</div>
@endif

@php $isAdmin = $isAdmin ?? (bool) (auth()->user()?->is_admin); @endphp

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>{{ __('pages.kola.col_date') }}</th>
        <th>{{ __('pages.kola.col_deadline') }}</th>
        <th>{{ __('pages.kola.col_name') }}</th>
        <th>{{ __('pages.kola.col_evaluated') }}</th>
        @if ($isAdmin)<th>{{ __('pages.kola.col_actions') }}</th>@endif
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
                  <form action="{{ route('kola.vyhodnotit', $k->id) }}" method="post">@csrf<button type="submit" class="btn btn-primary btn-sm">{{ __('pages.kola.btn_evaluate') }}</button></form>
                  <form action="{{ route('kola.uzavrit', $k->id) }}" method="post">@csrf<button type="submit" class="btn btn-ghost btn-sm">{{ __('pages.kola.btn_close') }}</button></form>
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
