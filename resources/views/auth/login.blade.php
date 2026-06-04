{{-- Přihlášení. --}}
@extends('layouts.app')

@section('title', 'Přihlášení – VKV PA')

@push('head')
<style>
    /* ── Přihlášení – samostatný, izolovaný vzhled (prefix .lgn) ─────────── */
    .lgn { color: #2b2f3a; line-height: 1.4; }
    .lgn * { box-sizing: border-box; }

    .lgn-card {
        max-width: 300px; margin: 22px auto; background: #fff;
        border: 1px solid #e6e8ee; border-radius: 11px; overflow: hidden;
        box-shadow: 0 6px 18px -12px rgba(40, 45, 60, .28);
    }

    .lgn-card__head { padding: 15px 18px 12px; border-bottom: 1px solid #eef0f4; }
    .lgn-card__title { font-size: 15px; margin: 0 0 2px; color: #2b2f3a; border-bottom: 0; font-weight: 700; }
    .lgn-card__sub { font-size: 11.5px; color: #8a8f9c; margin: 0; }

    .lgn-card__body { padding: 15px 18px 18px; }

    .lgn-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
    .lgn-field__label {
        font-size: 10.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .04em; color: #9097a3;
    }
    .lgn-input {
        appearance: none; font: inherit; font-size: 13px; width: 100%; padding: 7px 10px;
        border: 1px solid #d9dce4; border-radius: 7px; background: #fcfcfd; color: #2b2f3a;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .lgn-input:focus {
        outline: 0; border-color: #9aa0bd; background: #fff;
        box-shadow: 0 0 0 3px rgba(86, 93, 140, .15);
    }

    .lgn-btn {
        appearance: none; border: 0; cursor: pointer; font: inherit; font-size: 13px; font-weight: 700;
        width: 100%; margin-top: 4px; padding: 8px 16px; border-radius: 7px;
        background: #565d8c; color: #fff; transition: background .15s ease;
    }
    .lgn-btn:hover { background: #454a73; color: #fff; }

    .lgn-alert {
        border-radius: 8px; padding: 9px 12px; margin-bottom: 13px; font-size: 12px;
        background: #fdeceb; border: 1px solid #f4c4bf; color: #8d231a;
    }
</style>
@endpush

@section('content')
<div class="lgn">
    <form class="lgn-card" action="{{ route('login') }}" method="post">
        @csrf
        <div class="lgn-card__head">
            <h1 class="lgn-card__title">Přihlášení administrátora</h1>
            <p class="lgn-card__sub">Vstup do správy provozního aktivu.</p>
        </div>

        <div class="lgn-card__body">
            @if ($errors->any())
                <div class="lgn-alert">{{ $errors->first() }}</div>
            @endif

            <div class="lgn-field">
                <label class="lgn-field__label" for="lgn-username">Jméno</label>
                <input class="lgn-input" type="text" id="lgn-username" name="username"
                       value="{{ old('username') }}" autocomplete="username" autofocus>
            </div>

            <div class="lgn-field">
                <label class="lgn-field__label" for="lgn-heslo">Heslo</label>
                <input class="lgn-input" type="password" id="lgn-heslo" name="heslo"
                       autocomplete="current-password">
            </div>

            <button type="submit" class="lgn-btn" name="poslane_heslo" value="šup tam">šup tam</button>
        </div>
    </form>
</div>
@endsection
