<?php
/**
 * Created by PhpStorm.
 * User: Lyn
 * Date: 2024/7/30
 * Time: 10:08
 */

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'cas_tickets';

    protected $dates = ['expire_at'];

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'ticket', 'expire_at'
    ];
}