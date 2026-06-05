{{-- Hlavní navigace. Použito ve dvou kontextech (desktop sidebar + mobilní drawer). --}}
<nav>
@unless($isAdmin)
    @foreach(config('navigation.public') as $item)
        @include('partials.menu-item', $item)
    @endforeach
    <a href="{{ route('login') }}" class="nav-link mt-3">Přihlášení admin</a>
@else
    <p class="nav-heading">Veřejná část</p>
    @foreach(config('navigation.public') as $item)
        @include('partials.menu-item', $item)
    @endforeach

    <p class="nav-heading">Administrace</p>
    @foreach(config('navigation.admin') as $item)
        @include('partials.menu-item', $item)
    @endforeach
    @foreach(config('navigation.admin_external') as $item)
        @include('partials.menu-item', $item)
    @endforeach

    <div class="mt-4 border-t border-line pt-3 text-xs text-muted">
        <p class="mb-2">Přihlášen: <span class="font-medium text-ink">{{ $adminName }}</span></p>
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm w-full">Odhlásit</button>
        </form>
    </div>
@endunless
</nav>
