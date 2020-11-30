<?php
/**
 * Created by PhpStorm.
 * User: Lyn
 * Date: 2020/11/30
 * Time: 22:20
 */

namespace Lyn\LaravelCasServer\Traits;


trait LynCasUser
{
    /**
     * Get user's name (should be unique in whole cas system)
     *
     * @return string
     */
    public function getName(){
        return 'name';
    }

    /**
     * Get user's attributes
     *
     * @return array
     */
    public function getCASAttributes(){

    }

    /**
     * @return Model
     */
    public function getEloquentModel(){

    }

}