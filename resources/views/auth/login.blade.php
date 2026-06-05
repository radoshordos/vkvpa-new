{{-- Přihlášení administrátora. --}}
@extends('layouts.app')

@section('title', 'Přihlášení – VKV PA')

@section('content')
<form class="card mx-auto max-w-sm overflow-hidden" action="{{ route('login') }}" method="post">
    @csrf
    <div class="border-b border-line px-5 py-4">
        <h1 class="!mb-0 !border-0 !pb-0 text-base text-heading">Přihlášení administrátora</h1>
        <p class="text-xs text-muted">Vstup do správy provozního aktivu.</p>
    </div>

    <div class="px-5 py-4">
        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        <div class="field">
            <label class="label" for="lgn-username">Jméno</label>
            <input class="input" type="text" id="lgn-username" name="username"
                   value="{{ old('username') }}" autocomplete="username" autofocus>
        </div>

        <div class="field">
            <label class="label" for="lgn-heslo">Heslo</label>
            <input class="input" type="password" id="lgn-heslo" name="heslo" autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary w-full" name="poslane_heslo" value="šup tam">šup tam</button>
    </div>
</form>
@endsection
