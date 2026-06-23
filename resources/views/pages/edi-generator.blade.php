{{--
    Ruční generátor EDI deníku – veřejný nástroj. Závodník zapíše hlavičku
    a spojení ručně a v reálném čase vidí složený .edi text, skóre a mapu
    (Livewire komponenta App\Livewire\EdiGenerator).
--}}
@extends('layouts.app')

@section('title', __('pages.generator.title'))
@section('meta_description', __('pages.generator.meta'))

@push('head')
  <link rel="preconnect" href="https://tile.openstreetmap.org">
  @vite('resources/js/edi-generator.js')
  <style>
    #edi-gen-mapa { height: 42vh; min-height: 320px; width: 100%; border-radius: .5rem; isolation: isolate; }
    .edi-preview { max-height: 40vh; overflow: auto; margin: 0; padding: .5rem .75rem;
                   font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .72rem;
                   line-height: 1.45; white-space: pre; border-radius: .375rem;
                   background: var(--surface-2, #f1f5f9); color: var(--ink, #1e293b); }
    .qso-input { width: 100%; min-width: 3.5rem; font: inherit; font-size: .8rem;
                 padding: .3rem .4rem; color: var(--ink); background-color: var(--surface);
                 border: 1px solid var(--line); border-radius: .375rem; }
    .qso-input:focus { outline: none; border-color: var(--brand);
                       box-shadow: 0 0 0 2px color-mix(in oklab, var(--brand) 22%, transparent); }
  </style>
@endpush

@section('content')

<h1>{{ __('pages.generator.heading') }}</h1>
<p class="max-w-prose text-sm text-muted mb-4">{{ __('pages.generator.intro') }}</p>

<livewire:edi-generator />

@endsection
