{{-- Samostatná stránka dlouhodobých trendů: 100% skládaný plošný graf podílu
     pásem napříč všemi vyhodnocenými koly, s přepínačem rozsahu let.
     Chart.js řídí trendy.js. --}}
@extends('layouts.app')

@section('title', __('pages.trendy.title'))
@section('meta_description', __('pages.trendy.meta'))

@push('head')
  @vite('resources/js/trendy.js')
@endpush

@section('content')

<script @cspNonce>
window.__trendyConfig = {
    pasmaTrend: @json($pasmaTrend),
    pasmaTrends: @json($pasmaTrends),
    t: { stations: @json(__('pages.stat.js_stations')) },
};
</script>

<header class="mb-4">
  <div class="text-xs font-semibold uppercase tracking-wide text-muted">
    <a href="{{ route('statistiky.index') }}" class="underline hover:text-heading">{{ __('pages.stat.index_heading') }}</a>
  </div>
  <h1 class="text-xl font-bold text-heading sm:text-2xl">{{ __('pages.trendy.heading') }}</h1>
  <p class="mt-1 text-sm text-muted">{{ __('pages.trendy.subtitle') }}</p>
</header>

@if ($pasmaTrend['rounds'] !== [])
<div class="mb-5 w-full rounded-lg border border-line bg-surface p-4">
  <div class="mb-3 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
    <span class="text-sm font-semibold text-heading">{{ __('pages.stat.chart_pasma') }}</span>
    <div class="flex flex-wrap gap-3">
      <div class="field mb-0 w-40">
        <label class="label" for="pasma-scope">{{ __('pages.trendy.scope_label') }}</label>
        <select id="pasma-scope" class="select" data-pasma-scope>
          <option value="all">{{ __('pages.trendy.scope_all') }}</option>
          <option value="domestic">{{ __('pages.trendy.scope_domestic') }}</option>
          <option value="dx">{{ __('pages.trendy.scope_dx') }}</option>
        </select>
      </div>
      <div class="field mb-0 w-40">
        <label class="label" for="pasma-years">{{ __('pages.trendy.years_label') }}</label>
        <select id="pasma-years" class="select" data-pasma-years>
          <option value="1">{{ trans_choice('pages.stat.pasma_year', 1) }}</option>
          <option value="3">{{ trans_choice('pages.stat.pasma_year', 3) }}</option>
          <option value="5">{{ trans_choice('pages.stat.pasma_year', 5) }}</option>
          <option value="0" selected>{{ __('pages.stat.pasma_all') }}</option>
        </select>
      </div>
    </div>
  </div>
  <div class="h-[calc(50vh-9rem)] min-h-[18rem] max-h-[28rem]"><canvas id="chartPasma" class="h-full w-full"></canvas></div>
  <p class="mt-2 hidden text-xs text-muted" data-pasma-empty>{{ __('pages.trendy.scope_empty') }}</p>
  <p class="mt-2 text-xs text-muted">{{ __('pages.trendy.pasma_note') }}</p>
</div>
@else
<p class="text-sm text-muted">{{ __('pages.trendy.empty') }}</p>
@endif

@endsection
