<?php

namespace Lyn\LaravelCasServer\Contracts\Interactions;

use Illuminate\Http\Request;
use Leo108\CAS\Contracts\Models\UserModel;
use Lyn\LaravelCasServer\Exceptions\CAS\CasException;
use Symfony\Component\HttpFoundation\Response;

interface UserPassword
{
    /**
     * Retrive user from credential in request
     *
     * @param Request $request
     * @return UserModel|null
     */
    public function reset(Request $request);


    /**
     * Show register page
     *
     * @param Request $request
     * @return Response
     */
    public function showResetPage(Request $request);

    /**
     * Send reset password code
     *
     * @param Request $request
     * @return mixed
     */
    public function sendResetCode(Request $request);

    /**
     * Show forget password page
     *
     * @param Request $request
     * @return mixed
     */
    public function showForgetPage(Request $request);
}
