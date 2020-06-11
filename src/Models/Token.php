<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $table = 'cas_tokens';

    protected $fillable = [
        'client_id', 'server_session_id', 'client_token'
    ];

    public function client()
    {
        $this->belongsTo('Lyn\LaravelCasServer\Models\Client', 'client_id');
    }
}
