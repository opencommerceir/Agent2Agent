<?php

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The `/showcase/enter` passcode form (Showcase prep, Phase 3, §7.33) —
 * deliberately holds no business logic and touches no Domain/Application
 * layer: a shared demo passcode has no real identity behind it worth
 * modeling as an Entity, unlike `LoginController`'s own
 * `AuthenticateUserAction` (a real `User` with a real password hash). A
 * plain string comparison against `config('showcase.passcode')`,
 * `hash_equals()`'d to avoid a timing side-channel, is the entire
 * "business rule" here.
 */
class ShowcaseAccessController extends Controller
{
    public function create(): View
    {
        return view('showcase.enter');
    }

    public function store(Request $request): RedirectResponse
    {
        $passcode = config('showcase.passcode');
        $submitted = (string) $request->input('passcode', '');

        if (! blank($passcode) && hash_equals((string) $passcode, $submitted)) {
            $request->session()->put('showcase_access_granted', true);

            return redirect()->route('showcase.index');
        }

        return redirect()->route('showcase.enter')->withErrors(['passcode' => t('showcase.gate.invalid')]);
    }
}
