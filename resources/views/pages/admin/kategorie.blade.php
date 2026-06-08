@extends('layouts.app')
@section('title', __('admin.kategorie_title'))
@section('content')

<h1>{{ __('admin.kategorie_heading') }}</h1>

{{-- announcement řeší centrální <x-flash /> v layoutu --}}

<div class="table-wrap mb-8">
    <table class="data-table">
        <thead>
            <tr>
                <th class="num">ID</th>
                <th>{{ __('admin.kategorie_col_name') }}</th>
                <th>{{ __('admin.kategorie_col_abbr') }}</th>
                <th>{{ __('admin.kategorie_col_desc') }}</th>
                <th class="num">dxid</th>
                <th>{{ __('admin.kategorie_col_actions') }}</th>
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
                    <td>
                        <a href="{{ route('kategorie.edit', $k->id) }}" class="icon-btn icon-btn-u" title="{{ __('admin.kategorie_btn_edit') }}">
                            <x-icon name="pencil" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="italic text-muted">{{ __('admin.kategorie_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Editační formulář (zobrazí se jen při edit akci) --}}
@isset($editKategorie)
<div class="card max-w-2xl p-5 mb-8 border-l-4 border-brand">
    <h2>{{ __('admin.kategorie_edit') }}: <span class="mono">{{ $editKategorie->nazev }}</span></h2>

    <x-form-errors class="mt-3" />

    <form method="post" action="{{ route('kategorie.update', $editKategorie->id) }}" class="mt-3 space-y-3">
        @csrf
        @method('PATCH')

        <div class="flex flex-wrap gap-3">
            <x-field name="nazev" id="edit-nazev" :label="__('admin.field_name')" required
                     wrapper="mb-0 min-w-48 flex-1" :value="old('nazev', $editKategorie->nazev)" maxlength="50" />
            <x-field name="zkratka" id="edit-zkratka" :label="__('admin.field_abbr')" required class="mono"
                     wrapper="mb-0 w-32" :value="old('zkratka', $editKategorie->zkratka)" maxlength="20" />
            {{-- dxid: 0 = tato kategorie je tuzemská; jinak ID odpovídající tuzemské kategorie --}}
            <x-field name="dxid" id="edit-dxid" label="dxid" type="number" required
                     wrapper="mb-0 w-24" :value="old('dxid', $editKategorie->dxid)" min="0" />
        </div>

        <x-field name="popis" id="edit-popis" :label="__('admin.field_desc')"
                 wrapper="mb-0" :value="old('popis', $editKategorie->popis)" maxlength="250" />

        <div class="flex justify-end gap-3">
            <a href="{{ route('kategorie.index') }}" class="btn btn-ghost">{{ __('admin.btn_cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('admin.kategorie_btn_save') }}</button>
        </div>
    </form>
</div>
@endisset

{{-- Formulář pro přidání nové kategorie --}}
<div class="card max-w-2xl p-5">
    <h2>{{ __('admin.kategorie_add') }}</h2>

    @unless (isset($editKategorie))
        <x-form-errors class="mt-3" />
    @endunless

    <form method="post" action="{{ route('kategorie.store') }}" class="mt-3 space-y-3">
        @csrf

        <div class="flex flex-wrap gap-3">
            <x-field name="nazev" :label="__('admin.field_name')" required
                     wrapper="mb-0 min-w-48 flex-1" :value="old('nazev')" maxlength="50" />
            <x-field name="zkratka" :label="__('admin.field_abbr')" required class="mono"
                     wrapper="mb-0 w-32" :value="old('zkratka')" maxlength="20" />
            <x-field name="dxid" label="dxid" type="number" required
                     wrapper="mb-0 w-24" :value="old('dxid', '0')" min="0" />
        </div>

        <x-field name="popis" :label="__('admin.field_desc')"
                 wrapper="mb-0" :value="old('popis')" maxlength="250" />

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('admin.kategorie_add') }}</button>
        </div>
    </form>
</div>

@endsection
