<?php

namespace Lyn\LaravelCasServer\Exceptions\CAS;
use Exception;

class CasException extends Exception
{
    const INVALID_REQUEST = 'INVALID_REQUEST';
    const INVALID_TICKET = 'INVALID_TICKET';
    const INVALID_SERVICE = 'INVALID_SERVICE';
    const INTERNAL_ERROR = 'INTERNAL_ERROR';
    const UNAUTHORIZED_SERVICE_PROXY = 'UNAUTHORIZED_SERVICE_PROXY';

    protected $casErrorCode;

    /**
     * CasException constructor.
     * @param string $casErrorCode
     * @param string $msg
     */
    public function __construct($casErrorCode, $msg = '')
    {
        parent::__construct();
        $this->casErrorCode = $casErrorCode;
        $this->message      = $msg;
    }

    /**
     * @return string
     */
    public function getCasErrorCode(): string
    {
        return $this->casErrorCode;
    }

    public function getCasMsg(): string
    {
        //todo translate error msg
        return $this->casErrorCode;
    }
}
