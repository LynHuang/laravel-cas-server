<?php

namespace Lyn\LaravelCasServer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Lyn\LaravelCasServer\Contracts\Interactions\UserLogin;
use Lyn\LaravelCasServer\Events\CasLogoutEvent;
use Lyn\LaravelCasServer\Facades\CasServer;
use Auth;

class LoginController extends Controller
{
    protected $loginInteraction;

    public function __construct(
        UserLogin $loginInteraction
    )
    {
        $this->loginInteraction = $loginInteraction;
    }

    public function getLogin(Request $request)
    {
        return $this->loginInteraction->showLoginPage($request);
    }

    public function postLogin(Request $request)
    {
        return $this->loginInteraction->login($request);
    }

    public function logout(Request $request)
    {
        if (config('casserver.user.cas_sso_logout'))
            event(new CasLogoutEvent(session()->getId()));
        $this->loginInteraction->logout($request);
        return redirect(route('cas.login') . '?from=' . $request->get('from'));
    }
}
