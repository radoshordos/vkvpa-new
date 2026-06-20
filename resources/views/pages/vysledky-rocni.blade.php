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
    <select id="rok" name="rok" class="select w-auto">
      @for ($y = (int) date('Y'); $y >= 2006; $y--)
        <option value="{{ $y }}" @selected($y === $rok)>{{ $y }}</option>
      @endfor
    </select>
  </div>
  <div class="field mb-0">
    <label class="label" for="kategorie">{{ __('pages.rocni.filter_category') }}</label>
    <select id="kategorie" name="kategorie" class="select w-auto">
      <option value="0" @selected($katId === 0)>{{ __('pages.rocni.filter_all') }}</option>
      @foreach ($kategorie as $kat)
        <option value="{{ $kat->id }}" @selected($katId === $kat->id)>{{ $kat->nazev }}</option>
      @endforeach
    </select>
  </div>
  <label class="flex items-center gap-2 pb-1 text-sm">
    <input id="qrp-rocni" type="checkbox" name="qrp" value="1" @checked(request()->boolean('qrp'))> {{ __('pages.rocni.filter_qrp') }}
  </label>
  <label class="flex items-center gap-2 pb-1 text-sm">
    <input id="lp-rocni" type="checkbox" name="lp" value="1" @checked(request()->boolean('lp'))> {{ __('pages.rocni.filter_lp') }}
  </label>
  <button type="submit" class="btn btn-primary">{{ __('pages.rocni.btn_show') }}</button>
</form>

<h2>{{ __('pages.rocni.subheading', ['year' => $rok]) }}</h2>

@if ($vysledky->isNotEmpty())
  <p class="mb-3 flex flex-wrap items-center gap-3 text-xs text-muted">
    <span>{{ __('pages.rocni.legend_label') }}</span>
    <span class="inline-flex items-center gap-1"><span class="cell-qrp inline-block h-3 w-3 rounded-sm"></span> {{ __('pages.rocni.legend_qrp') }}</span>
    <span class="inline-flex items-center gap-1"><span class="cell-lp inline-block h-3 w-3 rounded-sm"></span> {{ __('pages.rocni.legend_lp') }}</span>
  </p>
@endif

@push('scripts')
<script @cspNonce>
(function () {
    var form = document.getElementById('rok')?.closest('form');
    if (form) {
        document.getElementById('rok').addEventListener('change', function () { form.submit(); });
        document.getElementById('kategorie').addEventListener('change', function () { form.submit(); });
        document.getElementById('qrp-rocni').addEventListener('change', function () { form.submit(); });
        document.getElementById('lp-rocni').addEventListener('change', function () { form.submit(); });
    }
}());
</script>
@endpush

@forelse ($vysledky as $kategorieId => $radky)
  <div class="section-head">{{ $kategorie[$kategorieId]->nazev ?? ('Kategorie ' . $kategorieId) }}</div>
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
            <td class="mono font-semibold">{{ $r->znacka }}</td>
            @for ($m = 1; $m <= 12; $m++)
              @php($b = (int) $r->getAttribute('mesic_' . $m))
              @php($vk = $r->getAttribute('vykon_' . $m))
              @php($vykon = is_string($vk) ? \App\Enums\Vykon::tryFrom($vk) : null)
              @php($tint = $b > 0 && $vykon?->badgeVariant() ? 'cell-' . $vykon->badgeVariant() : null)
              <td @class(['num', 'text-muted', $tint]) @if ($tint) title="{{ $vykon->label() }}" @endif>{{ $b > 0 ? $b : '—' }}</td>
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
