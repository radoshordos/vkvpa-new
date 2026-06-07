@extends('layouts.app')
@section('title', 'Omezeno / Restricted – VKV PA')
@section('content')
  <h1>{{ __('app.edi_restricted_title') }}</h1>
  <p class="text-muted">{{ __('app.edi_restricted_body') }}</p>
  <p class="mt-3"><a href="{{ url('/') }}">← {{ __('app.back_home') }}</a></p>
@endsection
