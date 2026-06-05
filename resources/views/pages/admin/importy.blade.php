@extends('layouts.app')
@section('title', 'Hromadný import – Administrace VKV PA')
@section('content')

<h1>Hromadný import EDI deníků</h1>

<p class="mb-4 text-sm text-muted max-w-prose">
    Nahraj ZIP archiv obsahující EDI soubory (<code>.edi</code> / <code>.txt</code>).
    Každý soubor se zpracuje stejnou logikou jako individuální nahrání přes formulář hlášení.
    Kolo závodu se určí automaticky z <code>TDate</code> v hlavičce každého deníku.
    Chybné soubory se přeskočí a ve výsledku se zobrazí důvod.
    Maximálně 200 souborů na jeden import.
</p>

@if ($errors->any())
    <div class="alert alert-error mb-4">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card p-5 max-w-xl mb-8">
    <form method="post" action="{{ route('importy.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4">
        @csrf
        <div class="field mb-0 flex-1 min-w-48">
            <label class="label" for="zip">ZIP archiv (max 20 MB)</label>
            <input id="zip" name="zip" type="file" accept=".zip,application/zip"
                   class="input @error('zip') input-err @enderror" required>
            @error('zip')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary">Importovat</button>
    </form>
</div>

@if ($results)
    @php
        $statusColor = $results['errors'] > 0 ? 'alert-error' : ($results['imported'] > 0 ? 'alert-success' : 'alert-info');
    @endphp

    <div class="alert {{ $statusColor }} mb-4 flex flex-wrap gap-4">
        <span><b>{{ $results['total'] }}</b> souborů zpracováno</span>
        <span class="text-ok font-bold">✓ {{ $results['imported'] }} importováno</span>
        @if ($results['skipped'] > 0)
            <span class="text-muted">⟳ {{ $results['skipped'] }} přeskočeno (duplikát)</span>
        @endif
        @if ($results['errors'] > 0)
            <span class="text-danger font-bold">✕ {{ $results['errors'] }} chyb</span>
        @endif
    </div>

    @if (! empty($results['items']))
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Soubor</th>
                        <th>Stav</th>
                        <th>Značka</th>
                        <th>Kolo</th>
                        <th class="num">Body</th>
                        <th>Poznámka</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results['items'] as $item)
                        <tr>
                            <td class="mono text-sm">{{ $item['file'] }}</td>
                            <td>
                                @switch($item['status'])
                                    @case('ok')
                                        <span class="badge badge-ok">✓ importováno</span>
                                        @break
                                    @case('skip')
                                        <span class="badge badge-brand">přeskočeno</span>
                                        @break
                                    @default
                                        <span class="badge badge-danger">chyba</span>
                                @endswitch
                            </td>
                            <td class="mono font-bold">{{ $item['znacka'] ?? '—' }}</td>
                            <td class="text-sm">{{ $item['kolo'] ?? '—' }}</td>
                            <td class="num">{{ isset($item['body']) ? $item['body'] : '—' }}</td>
                            <td class="text-sm text-muted">{{ $item['reason'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

@endsection
