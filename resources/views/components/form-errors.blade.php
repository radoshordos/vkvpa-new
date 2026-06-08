{{--
    Souhrn všech validačních chyb jako seznam v červené hlášce.
    Anonymní Blade komponenta – nahrazuje opakovaný blok ve formulářích.

    Použití:
      <x-form-errors />
      <x-form-errors class="mt-3" />   {{-- doplňkové třídy na obal --}}

    Nevykreslí nic, pokud nejsou žádné chyby.
--}}
@if ($errors->any())
    <x-alert type="error" {{ $attributes->merge(['class' => 'mb-4']) }}>
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
