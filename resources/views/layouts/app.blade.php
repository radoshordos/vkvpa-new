{{--
    Hlavní layout aplikace (Fáze 3 migrace – extrakce z head.php/menu.php/bottom.php).

    Zachovává grafický design: stejné css/styl.css a stejné id/class kontejnery
    (#page, #banner, #mainmenu, ul.menu, #obsah, #copyright).

    Modernizace bez vlivu na vzhled: HTML5 doctype + lang="cs" místo XHTML 1.0
    Strict (oba režimy jsou standards mode, CSS se renderuje shodně).

    Proměnné (volitelné, předává controller):
      $active     – klíč aktivní položky menu (např. 'edit_hlaseni')
      $isAdmin    – je přihlášen administrátor? (default ze session – Fáze 4 nahradí Laravel Auth)
      $adminName  – jméno přihlášeného (zobrazení v menu)
--}}
@php
    // Fáze 10: admin stav přes Laravel Auth (legacy session most odstraněn).
    $active    = $active    ?? '';
    $isAdmin   = $isAdmin   ?? (bool) (auth()->user()?->is_admin);
    $adminName = $adminName ?? (string) (auth()->user()?->name ?? '');
@endphp
<!DOCTYPE html>
<html lang="cs">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="OK Activity contest">
    <meta name="keywords" content="HAMradio, OK, Activity contest">
    <link rel="stylesheet" href="{{ asset('css/styl.css') }}" type="text/css">
    <title>@yield('title', 'VKV PA - provozní aktiv')</title>
    <script>
      function add_text(t) {
        document.addmessage.textaktuality.value += '' + t + '';
      }
    </script>
    @stack('head')
  </head>
  <body>
    <div id="page">
      <div id="banner"><a href="{{ url('/') }}">VKV provozní aktiv</a></div>

      @include('partials.menu')

      <div id="obsah">
        @auth
          @if(! auth()->user()->is_admin)
            <a accesskey="0" href="{{ route('login') }}"></a>
          @endif
        @endauth

        @yield('content')
      </div>

      @include('partials.footer')
    </div>
    @stack('scripts')
  </body>
</html>
