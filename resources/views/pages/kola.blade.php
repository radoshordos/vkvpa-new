@extends('layouts.app')
@section('title', __('pages.kola.title'))
@section('content')
<h1>{{ __('pages.kola.heading') }}</h1>

{{-- announcement řeší centrální <x-flash /> v layoutu --}}

@php
    $isAdmin = $isAdmin ?? (bool) (auth()->user()?->is_admin);
    // Start závodu z konfigurace závodního okna ('0800' → '08:00')
    $startZavodu = preg_replace('/^(\d{2})(\d{2})$/', '$1:$2', \App\Support\ContestWindow::from());
@endphp

<div class="mb-4 flex flex-wrap gap-2">
  @if ($isAdmin)
    <a href="{{ route('kola.admin.create') }}" class="btn btn-primary btn-sm">{{ __('pages.kola.btn_create') }}</a>
  @endif
</div>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>{{ __('pages.kola.col_date') }}</th>
        <th>{{ __('pages.kola.col_deadline') }}</th>
        <th>{{ __('pages.kola.col_name') }}</th>
        <th class="text-right"><abbr title="{{ __('pages.kola.col_count') }}">Σ</abbr></th>
        <th>{{ __('pages.kola.col_state') }}</th>
        <th>{{ __('pages.kola.col_evaluated') }}</th>
        @if ($isAdmin)<th>{{ __('pages.kola.col_actions') }}</th>@endif
      </tr>
    </thead>
    <tbody>
      @foreach ($kola as $k)
        @php $stav = $k->stav(); @endphp
        <tr>
          <td class="whitespace-nowrap">{{ $k->datum_konani ? $k->datum_konani->locale(app()->getLocale())->isoFormat('dddd D. M. YYYY').' '.$startZavodu.' UTC' : '' }}</td>
          {{-- isoFormat dddd = název dne v aktuálním jazyce (pátek / Friday) --}}
          <td class="whitespace-nowrap">{{ $k->datum_uzaverky ? $k->datum_uzaverky->locale(app()->getLocale())->isoFormat('dddd D. M. YYYY HH:mm').' UTC' : '' }}</td>
          <td>{{ $k->nazev }}</td>
          <td class="text-right">{{ $k->hlaseni_count }}</td>
          <td>
            <span class="badge {{ $stav->badgeClass() }}">{{ $stav->label() }}</span>
          </td>
          <td class="whitespace-nowrap">{{ $k->vyhodnoceno?->format('j. n. Y H:i') ?? '—' }}</td>
          @if ($isAdmin)
            <td>
              <div class="flex flex-wrap gap-2">
                <a href="{{ route('kola.admin.edit', $k->id) }}" class="btn btn-ghost btn-sm">{{ __('pages.kola.btn_edit') }}</a>
                @unless ($k->vyhodnoceno)
                  <form action="{{ route('kola.vyhodnotit', $k->id) }}" method="post">@csrf<button type="submit" class="btn btn-primary btn-sm">{{ __('pages.kola.btn_evaluate') }}</button></form>
                  <form action="{{ route('kola.uzavrit', $k->id) }}" method="post">@csrf<button type="submit" class="btn btn-ghost btn-sm">{{ __('pages.kola.btn_close') }}</button></form>
                @endunless
              </div>
            </td>
          @endif
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
