<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Public API for Dynamic Popups (no auth required)
Route::get('/popups/{area}', 'Admin\DynamicPopupController@getPopupsForArea')->name('api.popups.area');
Route::post('/popups/{id}/dismiss', 'Admin\DynamicPopupController@dismissPopup')->name('api.popups.dismiss');
