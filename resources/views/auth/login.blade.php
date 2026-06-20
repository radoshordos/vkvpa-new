{{-- Přihlášení administrátora. --}}
@extends('layouts.app')

@section('title', __('admin.login_title'))

@section('content')
<form class="card mx-auto max-w-sm overflow-hidden" action="{{ route('login') }}" method="post">
    @csrf
    <div class="border-b border-line px-5 py-4">
        <h1 class="!mb-0 !border-0 !pb-0 text-base text-heading">{{ __('admin.login_heading') }}</h1>
        <p class="text-xs text-muted">{{ __('admin.login_subtitle') }}</p>
    </div>

    <div class="px-5 py-4">
        @if ($errors->any())
            <x-alert type="error" :message="$errors->first()" />
        @endif

        <x-field name="username" id="lgn-username" :label="__('admin.login_username')"
                 :value="old('username')" autocomplete="username" autofocus />

        <x-field name="heslo" id="lgn-heslo" type="password" :label="__('admin.login_password')"
                 autocomplete="current-password" />

        <button type="submit" class="btn btn-primary w-full" name="poslane_heslo" value="šup tam">{{ __('admin.login_btn') }}</button>
    </div>
</form>
@endsection
