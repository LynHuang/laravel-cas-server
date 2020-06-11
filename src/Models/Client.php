<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'cas_clients';

    protected $fillable = [
        'client_name', 'client_redirect', 'client_logout_callback'
    ];

    protected $casts = [
        'client_enabled' => 'boolean',
    ];

    public function tokens()
    {
        $this->hasMany('Lyn\LaravelCasServer\Models\Token', 'client_id');
    }
}
