{{-- Hlavní menu. --}}
<div id="mainmenu">
<ul class="menu">
@unless($isAdmin)
    @foreach(config('navigation.public') as $item)
        @include('partials.menu-item', $item)
    @endforeach
    <br>
    <li><a href="{{ route('login') }}">Přihlášení admin</a></li>
@else
    <li class="menu-heading">Veřejná část</li>
    @foreach(config('navigation.public') as $item)
        @include('partials.menu-item', $item)
    @endforeach

    <li class="menu-heading">Administrace</li>
    @foreach(config('navigation.admin') as $item)
        @include('partials.menu-item', $item)
    @endforeach
    @foreach(config('navigation.admin_external') as $item)
        @include('partials.menu-item', $item)
    @endforeach

    <br>
    <li>Přihlášen: {{ $adminName }}</li>
    <li>
      <form action="{{ route('logout') }}" method="post" style="display:inline;">
        @csrf
        <a href="#" onclick="this.closest('form').submit();return false;">Odhlásit</a>
      </form>
    </li>
@endunless
</ul>
</div>
