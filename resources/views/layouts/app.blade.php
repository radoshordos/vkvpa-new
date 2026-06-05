{{--
    Hlavní layout aplikace – responzivní (Tailwind v4).

    Proměnné (volitelné, předává controller):
      $active     – klíč aktivní položky menu (např. 'edit_hlaseni')
      $isAdmin    – je přihlášen administrátor?
      $adminName  – jméno přihlášeného (zobrazení v menu)
--}}
@php
    $active    = $active    ?? '';
    $isAdmin   = $isAdmin   ?? (bool) (auth()->user()?->is_admin);
    $adminName = $adminName ?? (string) (auth()->user()?->name ?? '');
@endphp
<!DOCTYPE html>
<html lang="cs" class="antialiased">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="OK Activity contest">
    <meta name="keywords" content="HAMradio, OK, Activity contest">
    <title>@yield('title', 'VKV PA - provozní aktiv')</title>

    {{-- Tmavý režim bez probliknutí: nastav třídu .dark dřív, než se vykreslí tělo. --}}
    <script>
      (function () {
        try {
          var t = localStorage.getItem('theme');
          if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
          }
        } catch (e) {}
      })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
  </head>
  <body class="min-h-screen bg-bg text-ink">

    {{-- ── Horní lišta ──────────────────────────────────────────────── --}}
    <header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur">
      <div class="mx-auto flex h-14 max-w-6xl items-center gap-3 px-4">
        {{-- Hamburger (mobil) --}}
        <button type="button" data-drawer-open
                class="-ml-1 inline-flex h-9 w-9 items-center justify-center rounded-lg text-ink hover:bg-surface-2 lg:hidden"
                aria-label="Otevřít menu">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>

        <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold tracking-tight text-heading">
          <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-brand text-xs font-bold text-brand-fg">PA</span>
          <span class="text-base sm:text-lg">VKV provozní aktiv</span>
        </a>

        <div class="ml-auto flex items-center gap-2">
          @auth
            <span class="hidden text-xs text-muted sm:inline">{{ $adminName }}</span>
          @endauth
          {{-- Přepínač tmavého režimu --}}
          <button type="button" data-theme-toggle
                  class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-ink hover:bg-surface-2"
                  aria-label="Přepnout tmavý režim">
            <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
            </svg>
            <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36l-1.42-1.42M7.05 7.05L5.64 5.64m12.72 0l-1.42 1.42M7.05 16.95l-1.41 1.41M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
          </button>
        </div>
      </div>
    </header>

    <div class="mx-auto flex max-w-6xl gap-6 px-4 py-6">
      {{-- ── Postranní navigace (desktop) ──────────────────────────── --}}
      <aside class="hidden w-52 shrink-0 lg:block">
        @include('partials.menu')
      </aside>

      {{-- ── Obsah ─────────────────────────────────────────────────── --}}
      <main class="content min-w-0 flex-1">
        @yield('content')
      </main>
    </div>

    @include('partials.footer')

    {{-- ── Mobilní off-canvas menu ───────────────────────────────────── --}}
    <div data-drawer-backdrop class="fixed inset-0 z-40 hidden bg-black/40 lg:hidden" data-drawer-close></div>
    <aside data-drawer
           class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full overflow-y-auto border-r border-line bg-surface p-4 transition-transform duration-200 lg:hidden">
      <div class="mb-3 flex items-center justify-between">
        <span class="font-semibold text-heading">Menu</span>
        <button type="button" data-drawer-close
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-surface-2" aria-label="Zavřít menu">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      @include('partials.menu')
    </aside>

    @stack('scripts')
  </body>
</html>
