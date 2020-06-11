<?php

namespace Lyn\LaravelCasServer\Contracts\Models;

use Illuminate\Database\Eloquent\Model;

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
}
