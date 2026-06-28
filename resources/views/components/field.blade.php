{{--
    Formulářové pole: label + ovládací prvek + chybová hláška (@error).
    Anonymní Blade komponenta – sjednocuje opakující se vzor ve formulářích.

    Textový input (výchozí):
      <x-field name="znacka" :label="__('pages.hlaseni.field_callsign')" :value="$val('znacka', $e->callsign ?? '')" required class="mono font-bold" />

    Vlastní prvek (select, textarea…) přes slot „control“:
      <x-field name="kolo" :label="..." required>
        <x-slot:control>
          <select name="kolo" @class(['select', 'input-err' => $errors->has('kolo')])>…</select>
        </x-slot:control>
      </x-field>

    Parametry:
      name     – jméno pole (povinné, použito i pro id a @error)
      label    – popisek; když není zadán, label se nevykreslí
      value    – hodnota textového inputu
      type     – typ textového inputu (výchozí: text)
      required – přidá hvězdičku k popisku a atribut „required“ k inputu
      id       – id prvku (výchozí: „f-{name}“)
      wrapper  – doplňkové třídy obalového <div class="field …">
      hint     – nápověda pod prvkem (drobný text)
--}}
@props([
    'name',
    'label' => null,
    'value' => '',
    'type' => 'text',
    'required' => false,
    'id' => null,
    'wrapper' => null,
    'hint' => null,
])
@php $id = $id ?? 'f-' . $name; @endphp
<div @class(['field', $wrapper => $wrapper])>
    @if ($label)
        <label class="label" for="{{ $id }}">{{ $label }}@if ($required) *@endif</label>
    @endif

    @isset($control)
        {{ $control }}
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" @required($required)
               {{ $attributes->class(['input', 'input-err' => $errors->has($name)]) }}>
    @endisset

    @if ($hint)<span class="mt-1 block text-xs text-muted">{{ $hint }}</span>@endif
    @error($name)<span class="field-error">{{ $message }}</span>@enderror
</div>
