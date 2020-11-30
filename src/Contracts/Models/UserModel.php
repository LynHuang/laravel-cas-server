<?php

namespace Lyn\LaravelCasServer\Contracts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * cas user model interface
 *
 * Interface UserModel
 * @package Lyn\LaravelCasServer\Contracts\Models
 */
interface UserModel
{
    /**
     * Get user's name (should be unique in whole cas system)
     *
     * @return string
     */
    public function getName();

    /**
     * Get user's attributes
     *
     * @return array
     */
    public function getCASAttributes();

    /**
     * @return Model
     */
    public function getEloquentModel();


    public function passwordCheck();
}
