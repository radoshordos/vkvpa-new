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
@endphp
<div {{ $attributes->class(['alert', $variant]) }}>
    {{ $message ?? $slot }}
</div>
