@extends('layouts.app')
@section('title', 'Roční výsledky – VKV PA')
@section('content')
<h1>Roční výsledky / Year results</h1>

<form method="get" action="{{ route('rocni_vysledky') }}" class="card mb-4 flex flex-wrap items-end gap-4 p-3">
  <div class="field mb-0">
    <label class="label" for="rok">Rok / Year</label>
    <select id="rok" name="rok" class="select w-auto">
      @for ($y = (int) date('Y'); $y >= 2006; $y--)
        <option value="{{ $y }}" @selected($y === $rok)>{{ $y }}</option>
      @endfor
    </select>
  </div>
  <label class="flex items-center gap-2 pb-1 text-sm">
    <input id="qrp-rocni" type="checkbox" name="qrp" value="1" @checked(request()->boolean('qrp'))> jen QRP
  </label>
  <button type="submit" class="btn btn-primary">Vypsat</button>
</form>

<h2>Výsledková listina za rok {{ $rok }}</h2>

@push('scripts')
<script>
(function () {
    var form = document.getElementById('rok')?.closest('form');
    if (form) {
        document.getElementById('rok').addEventListener('change', function () { form.submit(); });
        document.getElementById('qrp-rocni').addEventListener('change', function () { form.submit(); });
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
          <th class="num" style="width:60px;">Pořadí</th>
          <th>Značka</th>
          <th class="num">Celkem</th>
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
  <p class="text-sm text-muted">Pro tento rok nejsou žádné vyhodnocené výsledky.</p>
@endforelse
@endsection
