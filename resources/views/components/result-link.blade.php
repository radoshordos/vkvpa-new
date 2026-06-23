@props(['href', 'title', 'aria' => null, 'target' => null])

<a href="{{ $href }}"
   @if ($target) target="{{ $target }}" @endif
   {{ $attributes->merge(['class' => 'icon-btn icon-btn-ghost icon-btn-label']) }}
   title="{{ $title }}"
   aria-label="{{ $aria ?? $title }}">{{ $slot }}</a>
