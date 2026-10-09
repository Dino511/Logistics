<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS_PER_ACCOUNT = 5;

    private const MAX_ATTEMPTS_PER_IP = 20;

    private const LOCKOUT_SECONDS = 60;

    public function showLogin()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('Please agree to the Terms of Service and Privacy Policy to sign in.'),
        ]);
        unset($credentials['terms']);

        // Two limits: repeated guesses at one account, and one address trying many accounts.
        $accountKey = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        $ipKey = 'login-ip:'.$request->ip();

        foreach ([[$accountKey, self::MAX_ATTEMPTS_PER_ACCOUNT], [$ipKey, self::MAX_ATTEMPTS_PER_IP]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);
                ActivityLog::record('login_locked', 'Sign-in blocked after too many failed attempts for '.$credentials['email']);

                return back()->withErrors(['email' => __('Too many sign-in attempts. Please try again in :seconds seconds.', ['seconds' => $seconds])])->onlyInput('email');
            }
        }

        if (Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            RateLimiter::clear($accountKey);
            $request->session()->regenerate();
            ActivityLog::record('login', 'Signed in');

            return redirect()->intended('/dashboard');
        }

        RateLimiter::hit($accountKey, self::LOCKOUT_SECONDS);
        RateLimiter::hit($ipKey, self::LOCKOUT_SECONDS);

        // Right password but a deactivated account: say so. Only after the password is
        // confirmed, so the message can't be used to find out which accounts exist.
        if (Auth::validate($credentials)) {
            ActivityLog::record('login_failed', 'Sign-in refused for '.$credentials['email'].': account is deactivated');

            return back()->withErrors(['email' => __('Your account has been deactivated. Please contact your administrator.')])->onlyInput('email');
        }

        // Never log the password, only which account was tried.
        ActivityLog::record('login_failed', 'Failed sign-in attempt for '.$credentials['email']);

        return back()->withErrors(['email' => __('Invalid email or password.')])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        ActivityLog::record('logout', 'Signed out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
