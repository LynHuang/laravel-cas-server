<?php

namespace Lyn\LaravelCasServer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Lyn\LaravelCasServer\Contracts\Interactions\UserPassword;

class PasswordController extends Controller
{
    protected $passwordInteraction;

    public function __construct(UserPassword $passwordInteraction)
    {
        $this->passwordInteraction = $passwordInteraction;
    }

    public function passwordGetReset(Request $request)
    {
        return $this->passwordInteraction->showResetPage($request);
    }

    public function passwordPostReset(Request $request)
    {
        return $this->passwordInteraction->reset($request);
    }

    public function passwordForget(Request $request)
    {
        return $this->passwordInteraction->showForgetPage($request);
    }

    public function passwordSendCode(Request $request)
    {
        return $this->passwordInteraction->sendResetCode($request);
    }
}
