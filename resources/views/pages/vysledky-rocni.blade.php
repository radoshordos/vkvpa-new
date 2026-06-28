@extends('layouts.app')
@section('title', __('pages.rocni.title'))
@section('meta_description', __('pages.rocni.meta'))
{{-- Každý ročník je samostatný obsah → canonical nese ?rok (aktuální rok bez
     parametru, aby odpovídal odkazu v navigaci a sitemapě). --}}
@section('canonical', route('rocni_vysledky', $rok === (int) date('Y') ? [] : ['rok' => $rok]))
@section('content')
<h1>{{ __('pages.rocni.heading') }}</h1>

<form method="get" action="{{ route('rocni_vysledky') }}" class="card mb-4 flex flex-wrap items-end gap-4 p-3">
  <div class="field mb-0">
    <label class="label" for="rok">{{ __('pages.rocni.filter_year') }}</label>
    <select id="rok" name="rok" class="select w-auto" data-autosubmit>
      @for ($y = (int) date('Y'); $y >= 2006; $y--)
        <option value="{{ $y }}" @selected($y === $rok)>{{ $y }}</option>
      @endfor
    </select>
  </div>
  <div class="field mb-0">
    <label class="label" for="band">{{ __('pages.rocni.filter_band') }}</label>
    <select id="band" name="band" class="select w-auto" data-autosubmit>
      <option value="0" @selected($bandId === 0)>{{ __('pages.rocni.filter_all') }}</option>
      @foreach ($bands as $band)
        <option value="{{ $band->id }}" @selected($bandId === $band->id)>{{ $band->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="field mb-0">
    <label class="label" for="kategorie">{{ __('pages.rocni.filter_category') }}</label>
    <select id="kategorie" name="kategorie" class="select w-auto" data-autosubmit>
      <option value="0" @selected($katId === 0)>{{ __('pages.rocni.filter_all') }}</option>
      @foreach ($kategorie as $kat)
        <option value="{{ $kat->id }}" @selected($katId === $kat->id)>{{ $kat->name }}</option>
      @endforeach
    </select>
  </div>
  <label class="flex items-center gap-2 pb-1 text-sm">
    <input id="qrp-rocni" type="checkbox" name="qrp" value="1" data-autosubmit @checked(request()->boolean('qrp'))> {{ __('pages.rocni.filter_qrp') }}
  </label>
  <label class="flex items-center gap-2 pb-1 text-sm">
    <input id="lp-rocni" type="checkbox" name="lp" value="1" data-autosubmit @checked(request()->boolean('lp'))> {{ __('pages.rocni.filter_lp') }}
  </label>
  <button type="submit" class="btn btn-primary">{{ __('pages.rocni.btn_show') }}</button>
</form>

<h2>{{ __('pages.rocni.subheading', ['year' => $rok]) }}</h2>

@if ($vysledky->isNotEmpty())
  <p class="mb-3 flex flex-wrap items-center gap-3 text-xs text-muted">
    <span>{{ __('pages.rocni.legend_label') }}</span>
    <span class="inline-flex items-center gap-1"><span class="cell-qrp inline-block h-3 w-3 rounded-sm"></span> {{ __('pages.rocni.legend_qrp') }}</span>
    <span class="inline-flex items-center gap-1"><span class="cell-lp inline-block h-3 w-3 rounded-sm"></span> {{ __('pages.rocni.legend_lp') }}</span>
    <span>{{ __('pages.rocni.month_link_hint') }}</span>
  </p>
@endif

@forelse ($vysledky as $kategorieId => $radky)
  <div class="section-head">{{ $kategorie[$kategorieId]->name ?? ('Kategorie ' . $kategorieId) }}</div>
  <div class="table-wrap mb-4">
    <table class="data-table">
      <thead>
        <tr>
          <th class="num" style="width:60px;">{{ __('pages.rocni.col_pos') }}</th>
          <th>{{ __('pages.rocni.col_callsign') }}</th>
          @for ($m = 1; $m <= 12; $m++)
            <th class="num">{{ sprintf('%02d', $m) }}</th>
          @endfor
          <th class="num">{{ __('pages.rocni.col_total') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($radky as $i => $r)
          <tr>
            <td class="num">{{ $i + 1 }}.</td>
            <td class="mono font-semibold">{{ $r->callsign }}</td>
            @for ($m = 1; $m <= 12; $m++)
              @php($b = (int) $r->getAttribute('mesic_' . $m))
              @php($vk = $r->getAttribute('vykon_' . $m))
              @php($vykon = is_string($vk) ? \App\Enums\Vykon::tryFrom($vk) : null)
              @php($tint = $b > 0 && $vykon?->badgeVariant() ? 'cell-' . $vykon->badgeVariant() : null)
              @php($details = $r->getAttribute('detail_mesic_' . $m))
              @php($details = is_array($details) ? $details : [])
              @php($single = count($details) === 1 ? $details[0] : null)
              @php($title = $single !== null ? __('pages.rocni.month_title', ['round' => $single['round_name'], 'qso' => $single['qso_count'], 'qso_points' => $single['qso_points'], 'mult' => $single['multiplier'], 'points' => $single['points']]) : ($tint ? $vykon->label() : null))
              <td @class(['num', 'text-muted', $tint, 'year-score-cell' => $b > 0 && $single !== null]) @if ($title) title="{{ $title }}" @endif>
                @if ($b > 0 && $single !== null)
                  <a class="year-score-link" href="{{ $single['edi_head_id'] ? route('edi.vizualizace', ['head' => $single['edi_head_id']]) : route('statistiky.kolo', ['kolo' => $single['round_id']]) }}" aria-label="{{ $title }}" @if ($single['edi_head_id']) target="_blank" rel="noopener" @endif>{{ $b }}</a>
                @else
                  {{ $b > 0 ? $b : '—' }}
                @endif
              </td>
            @endfor
            <td class="num font-semibold">{{ (int) $r->celkem }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@empty
  <p class="text-sm text-muted">{{ __('pages.rocni.no_results') }}</p>
@endforelse
@endsection
