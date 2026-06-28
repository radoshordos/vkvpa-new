@extends('layouts.app')
@section('title', __('admin.deniky_title'))
@section('content')

<h1>{{ __('admin.deniky_heading') }}</h1>

<p class="mb-4 max-w-prose text-sm text-muted">
    {!! __('admin.deniky_desc', ['link' => '<a href="'.route('vysledkova_listina').'" class="link">'.__('admin.deniky_desc_link').'</a>']) !!}
</p>

@if ($deniky->isEmpty())
    <p class="text-muted">{{ __('admin.deniky_empty') }}</p>
@else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="num">ID</th>
                    <th>{{ __('admin.deniky_col_call') }}</th>
                    <th>{{ __('admin.deniky_col_date') }}</th>
                    <th>{{ __('admin.deniky_col_band') }}</th>
                    <th>{{ __('admin.deniky_col_round') }}</th>
                    <th class="num">{{ __('admin.deniky_col_qso') }}</th>
                    <th class="hidden @[520px]:table-cell">{{ __('admin.deniky_col_upload') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($deniky as $d)
                    @php
                        $tdate = $d->t_date ?? '';
                        $datum = strlen($tdate) >= 8
                            ? \Illuminate\Support\Carbon::createFromFormat('Ymd', substr($tdate, 0, 8))?->format('j. n. Y') ?? $tdate
                            : ($tdate ?: '—');
                    @endphp
                    <tr>
                        <td class="num text-muted">{{ $d->id }}</td>
                        <td class="mono font-bold">{{ $d->p_call ?: '—' }}</td>
                        <td class="whitespace-nowrap">{{ $datum }}</td>
                        <td class="mono text-sm">{{ $d->p_band ?: '—' }}</td>
                        <td class="text-sm">{{ $kola->get($d->round_id, '—') }}</td>
                        <td class="num">{{ $d->lines_count }}</td>
                        <td class="hidden whitespace-nowrap text-sm text-muted @[520px]:table-cell">{{ $d->stamp?->format('j. n. Y H:i') ?? '—' }}</td>
                        <td class="whitespace-nowrap text-sm">
                            <a href="{{ route('edi.debug.show', $d) }}" class="link">{{ __('admin.deniky_link_debug') }}</a>
                        </td>
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
