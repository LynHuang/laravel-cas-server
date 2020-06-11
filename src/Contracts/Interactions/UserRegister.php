<?php

namespace Lyn\LaravelCasServer\Contracts\Interactions;

use Illuminate\Http\Request;
use Leo108\CAS\Contracts\Models\UserModel;
use Lyn\LaravelCasServer\Exceptions\CAS\CasException;
use Symfony\Component\HttpFoundation\Response;

interface UserRegister
{
    /**
     * Retrive user from credential in request
     *
     * @param Request $request
     * @return UserModel|null
     */
    public function register(Request $request);


    /**
     * Show register page
     *
     * @param Request $request
     * @return Response
     */
    public function showRegisterPage(Request $request);
}
