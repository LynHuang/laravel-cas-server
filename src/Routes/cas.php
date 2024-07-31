<?php
use Illuminate\Support\Facades\Route;

Route::get('login', 'LoginController@getLogin')->middleware('cas_auth')->name('cas.login');
Route::get('register', 'AuthController@register')->middleware('cas_auth');

Route::post('login', 'LoginController@postLogin')->name('cas.post.login');
Route::post('register', 'AuthController@postRegister');

Route::get('logout', 'LoginController@logout');
Route::get('user/info', 'AuthController@getUserInfo')->middleware('cas_ticket_check')->name('cas.user_info');
Route::get('record-client-token', 'AuthController@recodeClientToken')->middleware('cas_ticket_check')->name('cas.recode_token');

//reset password
Route::group(['prefix' => 'password'], function (){
    Route::get('forget', 'PasswordController@passwordForget');
    Route::get('reset', 'PasswordController@passwordGetReset');
    Route::post('reset', 'PasswordController@passwordPostReset');
    Route::post('send-code', 'PasswordController@passwordSendCode');
});
