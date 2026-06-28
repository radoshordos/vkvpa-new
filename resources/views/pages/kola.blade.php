@extends('layouts.app')
@section('title', __('pages.kola.title'))
@section('content')
<h1>{{ __('pages.kola.heading') }}</h1>

{{-- announcement řeší centrální <x-flash /> v layoutu --}}

@php
    $isAdmin = $isAdmin ?? (bool) (auth()->user()?->is_admin);
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
        @php $stav = $k->state(); @endphp
        <tr>
          <td class="whitespace-nowrap">{{ $k->starts_at ? $k->starts_at->locale(app()->getLocale())->isoFormat('dddd D. M. YYYY HH:mm').' UTC' : '' }}</td>
          {{-- isoFormat dddd = název dne v aktuálním jazyce (pátek / Friday) --}}
          <td class="whitespace-nowrap">{{ $k->closes_at ? $k->closes_at->locale(app()->getLocale())->isoFormat('dddd D. M. YYYY HH:mm').' UTC' : '' }}</td>
          <td>{{ $k->name }}</td>
          <td class="text-right">{{ $k->entries_count }}</td>
          <td>
            <span class="badge {{ $stav->badgeClass() }}">{{ $stav->label() }}</span>
          </td>
          <td class="whitespace-nowrap">{{ $k->evaluated_at?->format('j. n. Y H:i') ?? '—' }}</td>
          @if ($isAdmin)
            <td>
              {{-- Vyhodnocení probíhá automaticky (po uzávěrce: vše převzato / 20 dní),
                   admin už kolo ručně neuzavírá – jen edituje termíny a název. --}}
              <a href="{{ route('kola.admin.edit', $k->id) }}" class="btn btn-ghost btn-sm">{{ __('pages.kola.btn_edit') }}</a>
            </td>
          @endif
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
