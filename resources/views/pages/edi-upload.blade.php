@extends('layouts.app')
@section('title', 'Načíst EDI – VKV PA')
@section('content')
<h1>Načíst EDI soubor / Import EDI file</h1>
@if ($errors->any())
  <p class="red">{{ $errors->first('upload') }}</p>
  @if (session('lineErrors'))
    @foreach (session('lineErrors') as $le)
      <span class="small">Chybný řádek: {{ $le }}</span><br>
    @endforeach
  @endif
@endif
<form action="{{ route('read_edi.store') }}" method="post" enctype="multipart/form-data">
  @csrf
  EDI soubor / EDI file: <input type="file" name="upload" size="50"><br>
  <input type="submit" value="nahrát / upload">
</form>
@endsection
