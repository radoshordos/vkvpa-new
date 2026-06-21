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

{{-- Vysvětlení a nahrávací zóna pod sebou. --}}
<div class="mt-4 max-w-2xl">
    {{-- Srozumitelné vysvětlení, co se s deníkem stane (před nahráním). --}}
    <x-alert type="info" class="mb-4">
        <strong class="block">{{ __('pages.vizualizer.info_title') }}</strong>
        <ul class="mt-1.5 list-disc space-y-1 pl-5">
            <li>{{ __('pages.vizualizer.info_results') }}</li>
            <li>{{ __('pages.vizualizer.info_link') }}</li>
            <li>{{ __('pages.vizualizer.info_anon') }}</li>
            <li>{{ __('pages.vizualizer.info_data') }}</li>
        </ul>
    </x-alert>

    <livewire:vizualizer-upload />
</div>

@endsection
