@extends('layouts.app')
@section('title', __('admin.kategorie_add'))
@section('content')

<h1>{{ __('admin.kategorie_add') }}</h1>

{{-- Formulář pro přidání nové kategorie (edi_category) --}}
<div class="card max-w-2xl p-5">
    <x-form-errors class="mt-3" />

    <form method="post" action="{{ route('kategorie.store') }}" class="mt-3 space-y-3">
        @csrf

        <div class="flex flex-wrap gap-3">
            <x-field name="id" label="ID" type="number" hint="{{ __('Ponech prázdné pro automatické přidělení.') }}"
                     wrapper="mb-0 w-24" :value="old('id')" min="1" />
            <x-field name="name" :label="__('admin.field_name')" required
                     wrapper="mb-0 min-w-48 flex-1" :value="old('name')" maxlength="50" />
        </div>

        <div class="flex flex-wrap gap-3">
            <x-field name="band" :label="__('admin.field_band')" required wrapper="mb-0 w-40">
                <x-slot:control>
                    <select id="f-band" name="band" @class(['select', 'input-err' => $errors->has('band')])>
                        @foreach (\App\Http\Requests\Admin\KategorieRequest::BANDS as $b)
                            <option value="{{ $b }}" @selected(old('band') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </x-slot:control>
            </x-field>
            <x-field name="section" :label="__('admin.field_section')" required wrapper="mb-0 w-32">
                <x-slot:control>
                    <select id="f-section" name="section" @class(['select', 'input-err' => $errors->has('section')])>
                        <option value="SO" @selected(old('section') === 'SO')>SO</option>
                        <option value="MO" @selected(old('section') === 'MO')>MO</option>
                    </select>
                </x-slot:control>
            </x-field>
            <x-field name="variant" :label="__('admin.field_variant')" required wrapper="mb-0 w-36">
                <x-slot:control>
                    <select id="f-variant" name="variant" @class(['select', 'input-err' => $errors->has('variant')])>
                        <option value="domestic" @selected(old('variant', 'domestic') === 'domestic')>domestic</option>
                        <option value="dx" @selected(old('variant') === 'dx')>dx</option>
                    </select>
                </x-slot:control>
            </x-field>
            <x-field name="dxid" label="dxid" type="number"
                     wrapper="mb-0 w-24" :value="old('dxid')" min="1" />
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('kategorie.index') }}" class="btn btn-ghost">{{ __('admin.btn_cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('admin.kategorie_add') }}</button>
        </div>
    </form>
</div>

@endsection
