<?php

namespace Lyn\LaravelCasServer\Contracts\Interactions;

use Illuminate\Http\Request;
use Leo108\CAS\Contracts\Models\UserModel;
use Lyn\LaravelCasServer\Exceptions\CAS\CasException;
use Symfony\Component\HttpFoundation\Response;

interface UserLogin
{
    /**
     * Retrive user from credential in request
     *
     * @param Request $request
     * @return UserModel|null
     */
    public function login(Request $request);

    /**
     * show error info
     * @param CasException $exception
     * @return mixed
     */
    public function showErrorPage(CasException $exception);

    /**
     * Show login page
     *
     * @param Request $request
     * @param array   $errors
     * @return Response
     */
    public function showLoginPage(Request $request, array $errors = []);


    /**
     * Execute logout logic (clear session / cookie etc)
     *
     * @param Request $request
     * @return Response;
     */
    public function logout(Request $request);

}
