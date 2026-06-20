@extends('layouts.app')
@section('title', __('admin.heslo_title'))
@section('content')

<h1>{{ __('admin.heslo_heading') }}</h1>

<div class="card max-w-md p-5 border-l-4 border-brand">
    <p class="text-sm text-muted">{{ __('admin.heslo_desc') }}</p>

    <x-form-errors class="mt-3" />

    <form method="post" action="{{ route('heslo.update') }}" class="mt-3 space-y-3">
        @csrf
        @method('PATCH')

        <x-field name="soucasne_heslo" id="f-soucasne-heslo" type="password"
                 :label="__('admin.heslo_current')" required autocomplete="current-password" wrapper="mb-0" />

        <x-field name="heslo" id="f-heslo" type="password"
                 :label="__('admin.heslo_new')" required autocomplete="new-password" wrapper="mb-0" />

        <x-field name="heslo_confirmation" id="f-heslo-confirmation" type="password"
                 :label="__('admin.heslo_confirm')" required autocomplete="new-password" wrapper="mb-0" />

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('admin.heslo_btn_save') }}</button>
        </div>
    </form>
</div>

@endsection
