@extends('layouts.app')
@section('title', __('app.err_404_title'))
@section('content')
  <h1>{{ __('app.err_404_heading') }}</h1>
  <p class="text-muted">{{ __('app.err_404_body') }}</p>
  <p class="mt-3"><a href="{{ url('/') }}">← {{ __('app.back_home') }}</a></p>
@endsection
