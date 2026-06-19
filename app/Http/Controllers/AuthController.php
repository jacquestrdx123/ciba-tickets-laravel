<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('authenticated')) {
            return redirect()->route('dashboard');
        }
        return Inertia::render('Login');
    }

    public function login(Request $request)
    {
        $input = $request->input('password', '');
        $expected = config('app.auth_password');

        if (!$expected) {
            return back()->withErrors(['password' => 'AUTH_PASSWORD is not configured on the server.']);
        }

        if (!hash_equals($expected, $input)) {
            return back()->withErrors(['password' => 'Invalid password.']);
        }

        $request->session()->put('authenticated', true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget('authenticated');
        $request->session()->regenerate();
        return redirect()->route('login');
    }
}
