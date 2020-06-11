<?php

namespace Lyn\LaravelCasServer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Cache;
use Lyn\LaravelCasServer\Contracts\Interactions\UserRegister;
use Lyn\LaravelCasServer\Facades\CasServer;
use Lyn\LaravelCasServer\Models\Token;
use Lyn\LaravelCasServer\Repositories\TokenRepository;
use Lyn\LaravelCasServer\Services\TicketManger;

class AuthController extends Controller
{
    protected $registerInteraction;
    protected $tokenRepository;

    public function __construct(
        UserRegister $registerInteraction,
        TokenRepository $tokenRepository
    )
    {
        $this->registerInteraction = $registerInteraction;
        $this->tokenRepository = $tokenRepository;
    }

    public function getRegister(Request $request)
    {
        return $this->registerInteraction->showRegisterPage($request);
    }

    public function postRegister(Request $request)
    {
        $this->registerInteraction->register($request);
        $from = $request->get('from');
        return redirect(route('cas.login') . '?from=' . $from);
    }

    public function getUserInfo(Request $request)
    {
        $ticket = $request->get('ticket');
        $ticketManger = new TicketManger($ticket);
        $data = $ticketManger->getDataByTicket();
        $user = $this->model->find($data['id']);
        return response()->json([
            'code' => 0,
            'status' => 'success',
            'data' => $user->only(config('casserver.user.user_info')),
        ]);
    }

    public function recodeClientToken(Request $request)
    {
        $ticket = $request->get('ticket');
        $ticketManger = new TicketManger($ticket);
        $data = $ticketManger->getDataByTicket();
        $token = $request->get('token');

        $user = $this->model->find($data['user_id']);
        $this->tokenRepository->tokenStore([
            'user_id' => $user->id,
            'session_id' => $data['server_session_id'],
            'client_id' => $data['client_id'],
            'client_token' => $token
        ]);

        $ticketManger->removeTicket();
        return response()->json([
            'code' => 0,
            'status' => 'success'
        ]);
    }
}
