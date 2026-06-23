<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Livewire\EdiGenerator;
use Illuminate\View\View;

/**
 * Ruční generátor EDI deníku – veřejná stránka s Livewire komponentou
 * {@see EdiGenerator}. Závodník zapíše hlavičku a spojení ručně,
 * v reálném čase vidí složený .edi text, skóre a mapu spojení a může deník
 * stáhnout nebo (na 144 MHz) rovnou podat jako hlášení.
 */
class EdiGeneratorController extends Controller
{
    public function create(): View
    {
        return view('pages.edi-generator', ['active' => '']);
    }
}
