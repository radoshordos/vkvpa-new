{{-- Hlavní navigace. Použito ve dvou kontextech (desktop sidebar + mobilní drawer). --}}
@php
    // Průběžné výsledky se nabízejí, jen když je nějaké kolo k průběžnému
    // zobrazení (aktivní a nevyhodnocené); once() = jeden dotaz na request.
    $showPribezne = once(fn (): bool => \App\Models\VkvpaKola::aktualniProPrubezne() !== null);
@endphp
<nav>
@unless($isAdmin)
    @foreach(config('navigation.public') as $item)
        @continue(($item['key'] ?? null) === 'pribezne_vysledky' && ! $showPribezne)
        @include('partials.menu-item', $item)
    @endforeach
@else
    <p class="nav-heading">{{ __('app.public_section') }}</p>
    @foreach(config('navigation.public') as $item)
        @continue(($item['key'] ?? null) === 'pribezne_vysledky' && ! $showPribezne)
        @include('partials.menu-item', $item)
    @endforeach

    <p class="nav-heading">{{ __('app.administration') }}</p>
    @foreach(config('navigation.admin') as $item)
        @include('partials.menu-item', $item)
    @endforeach

    <div class="mt-4 border-t border-line pt-3 text-xs text-muted">
        <p class="mb-2">{{ __('app.logged_in_as') }}: <span class="font-medium text-ink">{{ $adminName }}</span></p>
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm w-full">{{ __('app.logout') }}</button>
        </form>
    </div>
@endunless
</nav>
