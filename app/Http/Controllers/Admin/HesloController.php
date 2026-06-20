<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HesloRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** Administrace – změna vlastního hesla přihlášeného administrátora. */
class HesloController extends Controller
{
    public function edit(): View
    {
        return view('pages.admin.heslo', [
            'active' => 'heslo.edit',
        ]);
    }

    public function update(HesloRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Cast 'password' => 'hashed' na modelu zajistí zahašování.
        $user->update(['password' => $request->string('heslo')->value()]);

        Log::info('admin.heslo.update', ['user' => $user->name]);

        return redirect()
            ->route('heslo.edit')
            ->with('announcement', 'Heslo bylo změněno.');
    }
}
