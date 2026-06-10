@extends('layouts.app')
@section('title', __('pages.rocni.title'))
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
  <label class="flex items-center gap-2 pb-1 text-sm">
    <input id="qrp-rocni" type="checkbox" name="qrp" value="1" @checked(request()->boolean('qrp'))> {{ __('pages.rocni.filter_qrp') }}
  </label>
  <label class="flex items-center gap-2 pb-1 text-sm">
    <input id="lp-rocni" type="checkbox" name="lp" value="1" @checked(request()->boolean('lp'))> {{ __('pages.rocni.filter_lp') }}
  </label>
  <button type="submit" class="btn btn-primary">{{ __('pages.rocni.btn_show') }}</button>
</form>

<h2>{{ __('pages.rocni.subheading', ['year' => $rok]) }}</h2>

@push('scripts')
<script @cspNonce>
(function () {
    var form = document.getElementById('rok')?.closest('form');
    if (form) {
        document.getElementById('rok').addEventListener('change', function () { form.submit(); });
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
          <th class="num">{{ __('pages.rocni.col_total') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($radky as $i => $r)
          <tr>
            <td class="num">{{ $i + 1 }}.</td>
            <td class="mono font-semibold">{{ $r->znacka }}</td>
            <td class="num">{{ (int) $r->celkem }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@empty
  <p class="text-sm text-muted">{{ __('pages.rocni.no_results') }}</p>
@endforelse
@endsection
