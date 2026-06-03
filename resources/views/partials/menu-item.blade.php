{{--
    Jedna položka menu.
    Vstup: $label (HTML), volitelně $key (název routy) nebo $url (externí), $target.
--}}
@php
    $href     = isset($key) ? route($key) : ($url ?? '#');
    $isActive = isset($key) && request()->routeIs($key);
@endphp
<li><a href="{{ $href }}"@class(['active' => $isActive])@isset($target) target="{{ $target }}"@endisset>{!! $label !!}</a></li>
