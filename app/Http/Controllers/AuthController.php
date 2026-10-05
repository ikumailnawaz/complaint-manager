<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $input = trim($request->input('email') ?: $request->input('login', ''));
        $password = $request->input('password', '');

        if (empty($input) || empty($password)) {
            return back()->withErrors([
                'email' => 'Please provide both username/email and password.',
            ])->withInput();
        }

        $cleanInput = strtolower($input);

        // Find user by email or by name (case-insensitive)
        $user = User::where('email', $cleanInput)
            ->orWhereRaw('LOWER(name) = ?', [$cleanInput])
            ->orWhere('email', $cleanInput . '@banksupport.com')
            ->first();

        if ($user && (\Illuminate\Support\Facades\Hash::check($password, $user->password) || \Illuminate\Support\Facades\Hash::check(strtolower($password), $user->password))) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            $target = $user->isOfficeStaff() ? 'tickets.open' : 'dashboard';
            return redirect()->intended(route($target));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function quickLogin($role)
    {
        $user = match ($role) {
            'super_admin', 'kumail' => User::whereRaw('LOWER(name) = ?', ['kumail'])->first(),
            'admin', 'ronald' => User::whereRaw('LOWER(name) = ?', ['ronald'])->first(),
            'superior', 'nazir' => User::whereRaw('LOWER(name) = ?', ['nazir'])->first(),
            'office_staff', 'staff', 'adnan' => User::whereRaw('LOWER(name) = ?', ['adnan'])->first(),
            'engineer', 'farhankhalid' => User::whereRaw('LOWER(name) = ?', ['farhankhalid'])->first(),
            'engineer2', 'usman' => User::whereRaw('LOWER(name) = ?', ['usman'])->first(),
            default => User::where('role', $role)->first() ?: User::first(),
        };

        if ($user) {
            Auth::login($user);
            request()->session()->regenerate();
            $target = $user->isOfficeStaff() ? 'tickets.open' : 'dashboard';
            return redirect()->route($target)->with('success', "Logged in as {$user->name} ({$user->role})");
        }

        return redirect()->route('login')->with('error', 'User not found.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
