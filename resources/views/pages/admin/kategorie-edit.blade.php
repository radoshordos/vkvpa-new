@extends('layouts.app')
@section('title', __('admin.kategorie_edit'))
@section('content')

<h1>{{ __('admin.kategorie_edit') }}: <span class="mono">{{ $editKategorie->name }}</span></h1>

{{-- Editační formulář kategorie (edi_categories: band × section × variant) --}}
<div class="card max-w-2xl p-5 border-l-4 border-brand">
    <x-form-errors class="mt-3" />

    <form method="post" action="{{ route('kategorie.update', $editKategorie->id) }}" class="mt-3 space-y-3">
        @csrf
        @method('PATCH')

        <x-field name="name" id="edit-name" :label="__('admin.field_name')" required
                 wrapper="mb-0" :value="old('name', $editKategorie->name)" maxlength="50" />

        <div class="flex flex-wrap gap-3">
            <x-field name="band" id="edit-band" :label="__('admin.field_band')" required wrapper="mb-0 w-40">
                <x-slot:control>
                    <select id="edit-band" name="band" @class(['select', 'input-err' => $errors->has('band')])>
                        @foreach (\App\Http\Requests\Admin\KategorieRequest::bands() as $b)
                            <option value="{{ $b }}" @selected(old('band', $editKategorie->band) === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </x-slot:control>
            </x-field>
            <x-field name="section" id="edit-section" :label="__('admin.field_section')" required wrapper="mb-0 w-32">
                <x-slot:control>
                    <select id="edit-section" name="section" @class(['select', 'input-err' => $errors->has('section')])>
                        <option value="SO" @selected(old('section', $editKategorie->section) === 'SO')>SO</option>
                        <option value="MO" @selected(old('section', $editKategorie->section) === 'MO')>MO</option>
                    </select>
                </x-slot:control>
            </x-field>
            <x-field name="variant" id="edit-variant" :label="__('admin.field_variant')" required wrapper="mb-0 w-36">
                <x-slot:control>
                    <select id="edit-variant" name="variant" @class(['select', 'input-err' => $errors->has('variant')])>
                        <option value="domestic" @selected(old('variant', $editKategorie->variant) === 'domestic')>domestic</option>
                        <option value="dx" @selected(old('variant', $editKategorie->variant) === 'dx')>dx</option>
                    </select>
                </x-slot:control>
            </x-field>
            {{-- dxid: prázdné = tato kategorie je tuzemská; jinak ID tuzemského protějšku DX řádku --}}
            <x-field name="dxid" id="edit-dxid" label="dxid" type="number"
                     wrapper="mb-0 w-24" :value="old('dxid', $editKategorie->dxid)" min="1" />
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('kategorie.index') }}" class="btn btn-ghost">{{ __('admin.btn_cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('admin.kategorie_btn_save') }}</button>
        </div>
    </form>
</div>

@endsection
