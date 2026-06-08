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
    $locale    = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" class="antialiased">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Elektronické hlášení výsledků závodů VKV PA – nahrávání EDI deníků, bodování a výsledkové listiny.">
    <meta name="keywords" content="HAMradio, OK, Activity contest, VKV, EDI, závod">
    <meta name="theme-color" content="#4338ca">
    <title>@yield('title', 'VKV PA - provozní aktiv')</title>

    {{-- Ikony + PWA manifest --}}
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/icon.svg">
    <link rel="apple-touch-icon" href="/icon.svg">
    <link rel="manifest" href="/site.webmanifest">

    {{-- Open Graph / Twitter – náhled při sdílení --}}
    @php $ogImage = asset('screenshots/vysledky.png'); @endphp
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="VKV provozní aktiv">
    <meta property="og:title" content="@yield('title', 'VKV provozní aktiv')">
    <meta property="og:description" content="Elektronické hlášení výsledků závodů VKV PA – nahrávání EDI deníků, bodování a výsledkové listiny.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ $locale === 'en' ? 'en_US' : 'cs_CZ' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'VKV provozní aktiv')">
    <meta name="twitter:description" content="Elektronické hlášení výsledků závodů VKV PA.">
    <meta name="twitter:image" content="{{ $ogImage }}">

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
                aria-label="{{ __('app.open_menu') }}">
          <x-icon name="menu" class="h-5 w-5" />
        </button>

        <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold tracking-tight text-heading">
          <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-brand text-xs font-bold text-brand-fg">PA</span>
          <span class="text-base sm:text-lg">{{ __('app.site_title') }}</span>
        </a>

        <div class="ml-auto flex items-center gap-2">
          @auth
            <span class="hidden text-xs text-muted sm:inline">{{ $adminName }}</span>
          @endauth

          {{-- Přepínač jazyka / Language switcher --}}
          <div class="flex items-center gap-0.5 text-xs font-semibold">
            <a href="{{ route('lang.switch', 'cs') }}"
               @class(['inline-flex h-7 items-center rounded px-1.5 transition-colors',
                        'bg-brand text-brand-fg'          => $locale === 'cs',
                        'text-muted hover:text-ink'        => $locale !== 'cs'])
               aria-label="Česky">CS</a>
            <span class="text-muted/50">|</span>
            <a href="{{ route('lang.switch', 'en') }}"
               @class(['inline-flex h-7 items-center rounded px-1.5 transition-colors',
                        'bg-brand text-brand-fg'          => $locale === 'en',
                        'text-muted hover:text-ink'        => $locale !== 'en'])
               aria-label="English">EN</a>
          </div>

          {{-- Přepínač tmavého režimu --}}
          <button type="button" data-theme-toggle
                  class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-ink hover:bg-surface-2"
                  aria-label="{{ __('app.toggle_theme') }}">
            <x-icon name="moon" class="h-5 w-5 dark:hidden" />
            <x-icon name="sun" class="hidden h-5 w-5 dark:block" />
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
        <x-flash />
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
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-surface-2" aria-label="{{ __('app.close_menu') }}">
          <x-icon name="close" class="h-5 w-5" />
        </button>
      </div>
      @include('partials.menu')
    </aside>

    @stack('scripts')

    {{-- Registrace service workeru (PWA / offline). V lokálním vývoji vynecháno. --}}
    <script>
      if ('serviceWorker' in navigator
          && location.hostname !== 'localhost'
          && location.hostname !== '127.0.0.1') {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('/sw.js').catch(function () {});
        });
      }
    </script>
  </body>
</html>
