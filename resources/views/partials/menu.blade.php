{{-- Hlavní navigace. Použito ve dvou kontextech (desktop sidebar + mobilní drawer). --}}
@php
    // Průběžné výsledky se nabízejí, jen když je nějaké kolo k průběžnému
    // zobrazení (aktivní a nevyhodnocené); once() = jeden dotaz na request.
    $showPribezne = once(fn (): bool => \App\Models\EdiRound::currentForStandings() !== null);
@endphp
<nav>
@foreach(config('navigation.menu') as $group)
    @php
        $items = array_filter($group['items'], fn (array $i): bool => ($isAdmin || ! ($i['admin'] ?? false))
            && (($i['key'] ?? null) !== 'pribezne_vysledky' || $showPribezne));
    @endphp
    @continue($items === [])

    {{-- Nadpisy skupin mají smysl jen pro admina; ne-admin vidí krátký plochý seznam. --}}
    @if($isAdmin)
        <p class="nav-heading">{{ __($group['heading']) }}</p>
    @endif
    @foreach($items as $item)
        @include('partials.menu-item', $item)
    @endforeach
@endforeach

@if($isAdmin)
    <div class="mt-4 border-t border-line pt-3 text-xs text-muted">
        <p class="mb-2">{{ __('app.logged_in_as') }}: <span class="font-medium text-ink">{{ $adminName }}</span></p>
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm w-full">{{ __('app.logout') }}</button>
        </form>
    </div>
@endif
</nav>
