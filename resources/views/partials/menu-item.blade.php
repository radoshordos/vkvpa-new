{{--
    Jedna položka menu.
    Vstup: $label (HTML), volitelně $key (název routy) nebo $url (externí), $target.
--}}
@php
    $href     = isset($key) ? route($key) : ($url ?? '#');
    $isActive = isset($key) && request()->routeIs($key);
@endphp
<a href="{{ $href }}"
   @class(['nav-link', 'active' => $isActive])
   @isset($target) target="{{ $target }}" rel="noopener" @endisset>{!! $label !!}</a>
