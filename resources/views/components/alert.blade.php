{{--
    Hláška (alert). Anonymní Blade komponenta.

    Použití:
      <x-alert type="success" :message="session('announcement')" />
      <x-alert type="error">Vlastní obsah, např. seznam chyb…</x-alert>

    Parametry:
      type    – success | error | warning | info (výchozí: info)
      message – text hlášky; když není zadán, vykreslí se slot
--}}
@props(['type' => 'info', 'message' => null])
@php
    $variant = [
        'success' => 'alert-success',
        'error'   => 'alert-error',
        'warning' => 'alert-warning',
        'info'    => 'alert-info',
    ][$type] ?? 'alert-info';

    $icon = [
        'success' => 'check-circle',
        'error'   => 'triangle-alert',
        'warning' => 'triangle-alert',
        'info'    => 'info-circle',
    ][$type] ?? 'info-circle';
@endphp
<div {{ $attributes->class(['alert', $variant]) }}>
    <div class="flex items-start gap-2.5">
        <x-icon :name="$icon" class="mt-0.5 h-4 w-4 flex-shrink-0" />
        <div class="min-w-0 flex-1">{{ $message ?? $slot }}</div>
    </div>
</div>
