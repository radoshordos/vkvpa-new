@extends('layouts.app')
@section('title', $kolo ? __('admin.kolo_edit_title') : __('admin.kolo_create_title'))
@section('content')

<h1>{{ $kolo ? __('admin.kolo_edit_heading') : __('admin.kolo_create_heading') }}</h1>

@if ($errors->any())
    <div class="alert alert-error mb-4">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card max-w-2xl p-5">
    <form method="post"
          action="{{ $kolo ? route('kola.admin.update', $kolo->id) : route('kola.admin.store') }}"
          class="space-y-4">
        @csrf
        @if ($kolo)
            @method('PATCH')
        @endif

        <div class="field">
            <label class="label" for="nazev">{{ __('admin.kolo_field_name') }} *</label>
            <input id="nazev" name="nazev" type="text"
                   class="input @error('nazev') input-err @enderror"
                   value="{{ old('nazev', $kolo?->nazev) }}"
                   maxlength="250" required>
            @error('nazev')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="grid gap-x-5 sm:grid-cols-2">
            <div class="field">
                <label class="label" for="datum_konani">{{ __('admin.kolo_field_date') }} *</label>
                <input id="datum_konani" name="datum_konani" type="date"
                       class="input @error('datum_konani') input-err @enderror"
                       value="{{ old('datum_konani', $kolo?->datum_konani?->format('Y-m-d')) }}"
                       required>
                @error('datum_konani')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label class="label" for="datum_uzaverky">{{ __('admin.kolo_field_deadline') }} *</label>
                <input id="datum_uzaverky" name="datum_uzaverky" type="datetime-local"
                       class="input @error('datum_uzaverky') input-err @enderror"
                       value="{{ old('datum_uzaverky', $kolo?->datum_uzaverky?->format('Y-m-d\TH:i')) }}"
                       required>
                @error('datum_uzaverky')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="field">
            <label class="label" for="poznamka">{{ __('admin.kolo_field_note') }}</label>
            <input id="poznamka" name="poznamka" type="text"
                   class="input @error('poznamka') input-err @enderror"
                   value="{{ old('poznamka', $kolo?->poznamka) }}"
                   maxlength="250">
            @error('poznamka')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="aktivni" value="0">
            <input id="aktivni" name="aktivni" type="checkbox" value="1"
                   @checked(old('aktivni', $kolo?->aktivni ?? false))>
            {{ __('admin.kolo_field_active') }}
        </label>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('kola.index') }}" class="btn btn-ghost">{{ __('admin.btn_cancel') }}</a>
            <button type="submit" class="btn btn-primary">
                {{ $kolo ? __('admin.kolo_btn_save') : __('admin.kolo_btn_create') }}
            </button>
        </div>
    </form>
</div>

@endsection
