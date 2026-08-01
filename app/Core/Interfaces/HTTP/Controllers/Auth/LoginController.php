<?php

namespace App\Core\Interfaces\HTTP\Controllers\Auth;

use App\Core\Application\Actions\AuthenticateUserAction;
use App\Core\Domain\Exceptions\InvalidCredentialsException;
use App\Core\Interfaces\HTTP\Requests\Auth\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The web-layer adapter between the login form and AuthenticateUserAction
 * — the same "verify identity (Action) vs. adapt it to this transport
 * (thin HTTP-layer class)" split AgentAuthenticationService already
 * demonstrates for MCP. AuthenticateUserAction has already confirmed the
 * credentials are genuinely valid before `Auth::loginUsingId()` is ever
 * called — this controller never re-checks a password itself.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly AuthenticateUserAction $authenticateUser,
    ) {
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $user = $this->authenticateUser->execute($request->string('email')->toString(), $request->string('password')->toString());
        } catch (InvalidCredentialsException) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => t('messages.auth.invalid_credentials')]);
        }

        Auth::loginUsingId($user->id, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard.index'));
    }
}
