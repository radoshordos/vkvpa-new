{{--
    Centrální flash hlášky ze session. Vkládá se jednou do layoutu nad obsah,
    takže jednotlivé stránky už nemusí řešit announcement/success/error samy.

    Stránkově specifické hlášky (např. importWarnings, lineErrors) zůstávají
    ve své stránce.
--}}
@if (session('announcement'))
    <x-alert type="success" :message="session('announcement')" />
@endif

@if (session('success'))
    <x-alert type="success" :message="session('success')" />
@endif

@if (session('error'))
    <x-alert type="error" :message="session('error')" />
@endif
