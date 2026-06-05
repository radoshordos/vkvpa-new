@extends('layouts.app')
@section('title', 'Načíst EDI – VKV PA')
@section('content')
<h1>Načíst EDI soubor / Import EDI file</h1>

@if ($errors->any())
  <div class="alert alert-error">
    {{ $errors->first('upload') }}
    @if (session('lineErrors'))
      @foreach (session('lineErrors') as $le)
        <br><span class="font-normal">Chybný řádek: {{ $le }}</span>
      @endforeach
    @endif
  </div>
@endif

<form action="{{ route('edi.store') }}" method="post" enctype="multipart/form-data" class="card flex flex-wrap items-end gap-3 p-4">
  @csrf
  <div class="field mb-0">
    <label class="label" for="upload">EDI soubor / EDI file</label>
    <input id="upload" type="file" name="upload" class="text-sm">
  </div>
  <button type="submit" class="btn btn-primary">nahrát / upload</button>
</form>
@endsection
