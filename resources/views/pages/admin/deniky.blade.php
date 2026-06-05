@extends('layouts.app')
@section('title', 'Nahrané EDI deníky – Administrace VKV PA')
@section('content')

<h1>Nahrané EDI deníky</h1>

<p class="mb-4 text-sm text-muted max-w-prose">
    Přehled všech deníků uložených v tabulce <code>edihead</code>.
    Podrobnosti (score, mapy, akce) jsou dostupné přes
    <a href="{{ route('vysledkova_listina') }}" class="link">výsledkovou listinu</a>.
</p>

@if ($deniky->isEmpty())
    <p class="text-muted">Žádné deníky zatím nebyly nahrány.</p>
@else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="num">ID</th>
                    <th>Značka</th>
                    <th>Datum závodu</th>
                    <th>Pásmo</th>
                    <th>Kolo</th>
                    <th class="num">QSO</th>
                    <th>Nahráno</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($deniky as $d)
                    @php
                        $tdate = $d->TDate ?? '';
                        $datum = strlen($tdate) >= 8
                            ? \Illuminate\Support\Carbon::createFromFormat('Ymd', substr($tdate, 0, 8))?->format('d.m.Y') ?? $tdate
                            : ($tdate ?: '—');
                    @endphp
                    <tr>
                        <td class="num text-muted">{{ $d->ID }}</td>
                        <td class="mono font-bold">{{ $d->PCall ?: '—' }}</td>
                        <td class="whitespace-nowrap">{{ $datum }}</td>
                        <td class="mono text-sm">{{ $d->PBand ?: '—' }}</td>
                        <td class="text-sm">{{ $kola->get($d->id_kola, '—') }}</td>
                        <td class="num">{{ $d->lines_count }}</td>
                        <td class="whitespace-nowrap text-sm text-muted">{{ $d->stamp?->format('d.m.Y H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $deniky->links() }}
    </div>
@endif

@endsection
