@extends('layouts.app')
@section('title', __('admin.kategorie_edit'))
@section('content')

<h1>{{ __('admin.kategorie_edit') }}: <span class="mono">{{ $editKategorie->nazev }}</span></h1>

{{-- Editační formulář kategorie --}}
<div class="card max-w-2xl p-5 border-l-4 border-brand">
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

        <div class="flex justify-end gap-3">
            <a href="{{ route('kategorie.index') }}" class="btn btn-ghost">{{ __('admin.btn_cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('admin.kategorie_btn_save') }}</button>
        </div>
    </form>
</div>

@endsection
