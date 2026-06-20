@extends('layouts.app')
@section('title', __('admin.kategorie_add'))
@section('content')

<h1>{{ __('admin.kategorie_add') }}</h1>

{{-- Formulář pro přidání nové kategorie --}}
<div class="card max-w-2xl p-5">
    <x-form-errors class="mt-3" />

    <form method="post" action="{{ route('kategorie.store') }}" class="mt-3 space-y-3">
        @csrf

        <div class="flex flex-wrap gap-3">
            <x-field name="id" label="ID" type="number" hint="{{ __('Ponech prázdné pro automatické přidělení.') }}"
                     wrapper="mb-0 w-24" :value="old('id')" min="1" />
            <x-field name="nazev" :label="__('admin.field_name')" required
                     wrapper="mb-0 min-w-48 flex-1" :value="old('nazev')" maxlength="50" />
            <x-field name="zkratka" :label="__('admin.field_abbr')" required class="mono"
                     wrapper="mb-0 w-32" :value="old('zkratka')" maxlength="20" />
            <x-field name="dxid" label="dxid" type="number" required
                     wrapper="mb-0 w-24" :value="old('dxid', '0')" min="0" />
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('kategorie.index') }}" class="btn btn-ghost">{{ __('admin.btn_cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('admin.kategorie_add') }}</button>
        </div>
    </form>
</div>

@endsection
