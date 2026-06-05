@extends('layouts.app')
@section('title', __('admin.kategorie_title'))
@section('content')

<h1>{{ __('admin.kategorie_heading') }}</h1>

@if (session('announcement'))
    <div class="alert alert-success">{{ session('announcement') }}</div>
@endif

<div class="table-wrap mb-8">
    <table class="data-table">
        <thead>
            <tr>
                <th class="num">ID</th>
                <th>{{ __('admin.kategorie_col_name') }}</th>
                <th>{{ __('admin.kategorie_col_abbr') }}</th>
                <th>{{ __('admin.kategorie_col_desc') }}</th>
                <th class="num">dxid</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($kategorie as $k)
                <tr>
                    <td class="num text-muted">{{ $k->id }}</td>
                    <td class="font-bold">{{ $k->nazev }}</td>
                    <td class="mono">{{ $k->zkratka }}</td>
                    <td class="text-sm text-muted">{{ $k->popis ?: '—' }}</td>
                    <td class="num">{{ $k->dxid }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="italic text-muted">{{ __('admin.kategorie_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Formulář pro přidání nové kategorie --}}
<div class="card max-w-2xl p-5">
    <h2>{{ __('admin.kategorie_add') }}</h2>

    @if ($errors->any())
        <div class="alert alert-error mb-4 mt-3">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('kategorie.store') }}" class="mt-3 space-y-3">
        @csrf

        <div class="flex flex-wrap gap-3">
            <div class="field mb-0 min-w-48 flex-1">
                <label class="label" for="nazev">{{ __('admin.field_name') }} *</label>
                <input id="nazev" name="nazev" type="text"
                       class="input @error('nazev') input-err @enderror"
                       value="{{ old('nazev') }}" maxlength="50" required>
                @error('nazev')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>
            <div class="field mb-0 w-32">
                <label class="label" for="zkratka">{{ __('admin.field_abbr') }} *</label>
                <input id="zkratka" name="zkratka" type="text"
                       class="input mono @error('zkratka') input-err @enderror"
                       value="{{ old('zkratka') }}" maxlength="20" required>
                @error('zkratka')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>
            <div class="field mb-0 w-24">
                {{-- TODO: dxid semantics TBD --}}
                <label class="label" for="dxid">dxid *</label>
                <input id="dxid" name="dxid" type="number"
                       class="input @error('dxid') input-err @enderror"
                       value="{{ old('dxid', '0') }}" min="0" required>
                @error('dxid')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="field mb-0">
            <label class="label" for="popis">{{ __('admin.field_desc') }}</label>
            <input id="popis" name="popis" type="text"
                   class="input @error('popis') input-err @enderror"
                   value="{{ old('popis') }}" maxlength="250">
            @error('popis')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('admin.kategorie_add') }}</button>
        </div>
    </form>
</div>

@endsection
