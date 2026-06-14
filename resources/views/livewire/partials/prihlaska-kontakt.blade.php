{{-- Editovatelná kontaktní pole – sdílí EDI náhled i ruční formulář. --}}
<div class="grid gap-x-5 sm:grid-cols-2">
    <div class="field">
        <label class="label" for="f-jmeno">{{ __('pages.hlaseni.field_name') }} *</label>
        <input id="f-jmeno" wire:model="jmeno" @class(['input', 'input-err' => $errors->has('jmeno')])>
        @error('jmeno')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label class="label" for="f-email">{{ __('pages.hlaseni.field_contact') }} *</label>
        <input id="f-email" type="email" wire:model="email" @class(['input', 'input-err' => $errors->has('email')])>
        @error('email')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <label class="label" for="f-telefon">{{ __('pages.hlaseni.field_phone') }} *</label>
        <input id="f-telefon" wire:model="telefon" @class(['input', 'input-err' => $errors->has('telefon')])>
        @error('telefon')<span class="field-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="field">
    <label class="label" for="f-poznamka">{{ __('pages.hlaseni.field_note') }}</label>
    <textarea id="f-poznamka" wire:model="poznamka" class="textarea" rows="2"></textarea>
    @error('poznamka')<span class="field-error">{{ $message }}</span>@enderror
</div>

<div class="field">
    <label class="label" for="f-soapbox">{{ __('pages.hlaseni.field_soapbox') }}</label>
    <textarea id="f-soapbox" wire:model="soapbox" class="textarea" rows="4"></textarea>
    @error('soapbox')<span class="field-error">{{ $message }}</span>@enderror
</div>
