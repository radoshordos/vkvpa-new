{{--
    Jedna položka menu.
    Vstup: 'trans' (klíč překladu) nebo 'label' (raw HTML), volitelně 'key' (název routy) nebo 'url' (externí), 'target'.
--}}
@php
    $href     = isset($key) ? route($key) : ($url ?? '#');
    $isActive = isset($key) && request()->routeIs($key);
    $text     = isset($trans) ? __($trans) : ($label ?? '');
@endphp
<a href="{{ $href }}"
   @class(['nav-link', 'active' => $isActive])
   @isset($target) target="{{ $target }}" rel="noopener" @endisset>{{ $text }}</a>
