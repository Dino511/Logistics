<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Switches the display language: saved on the account, or in the session before sign-in. */
class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(SetLocale::LOCALES))]]);

        if ($request->user()) {
            $request->user()->forceFill(['locale' => $data['locale']])->save();
        }
        $request->session()->put('locale', $data['locale']);

        return back();
    }
}
