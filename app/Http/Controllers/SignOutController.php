<?php

namespace App\Http\Controllers;

use App\Auth\AppSession;
use Illuminate\Http\RedirectResponse;

class SignOutController extends Controller
{
    /**
     * Revoke this device's token, then end the session. The session ends even if the API call fails.
     */
    public function __invoke(AppSession $session): RedirectResponse
    {
        $session->end();

        return redirect()->route('sign-in');
    }
}
