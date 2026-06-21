{{--
    Samostatný EDI Visualizer – nahrávací formulář. Veřejný nástroj: kdokoli
    nahraje svůj EDI deník a dostane sdílecí mapu spojení (viz show.blade.php).
--}}
@extends('layouts.app')

@section('title', __('pages.vizualizer.title'))
@section('meta_description', __('pages.vizualizer.meta'))

@section('content')

<h1>{{ __('pages.vizualizer.heading') }}</h1>
<p class="max-w-prose text-sm text-muted">{{ __('pages.vizualizer.intro') }}</p>

<div class="mt-4 max-w-xl">
    <livewire:vizualizer-upload />
</div>

@endsection
