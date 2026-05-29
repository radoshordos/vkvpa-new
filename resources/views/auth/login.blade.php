{{-- Přihlášení (Fáze 4) – nahrazuje login.php. Zachován vzhled formuláře. --}}
@extends('layouts.app')

@section('title', 'Přihlášení – VKV PA')

@section('content')
@if ($errors->any())
  <p class="red">{{ $errors->first() }}</p>
@endif
<table>
  <tr>
    <td>
      <form action="{{ route('login') }}" method="post">
        @csrf
        Jméno:
        <input type="text" name="username" value="{{ old('username') }}" />
    </td>
  </tr>
  <tr>
    <td>
      Heslo:
      <input type="password" name="heslo" />
    </td>
  </tr>
  <tr>
    <td>
      <input type="submit" name="poslane_heslo" value="šup tam" />
      </form>
    </td>
  </tr>
</table>
@endsection
