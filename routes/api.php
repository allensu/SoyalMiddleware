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

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});


Route::group(array('middleware' => ['api.response']), function()
{
//    Route::post('/connectDevice', 'ApiController@connectDevice');
//    Route::post('/addUID', 'ApiController@addUID');
//    Route::post('/removeUID', 'ApiController@removeUID');
//    Route::post('/expireUID', 'ApiController@expireUID');
//    Route::post('/updatePinCode', 'ApiController@updatePinCode');
//    Route::post('/setUidAlias', 'ApiController@setUidAlias');



    Route::post('/deviceRecordTest', 'SoyalApiController@deviceRecordTest');
//    Route::post('/socketTimeoutTest', 'SoyalApiController@socketTimeoutTest');
//    Route::post('/aba2wg', 'SoyalApiController@aba2wg');

    Route::post('/device-test', 'SoyalApiController@deviceTest');
    Route::post('/device-connect', 'SoyalApiController@deviceConnect');
    Route::post('/device', 'SoyalApiController@device');
    Route::post('/devices-async', 'SoyalApiController@devicesAsync');
    Route::post('/devices-update-pincode', 'SoyalApiController@devicesUpdatePincode');
//    Route::post('/device-uid-update-pincode', 'SoyalApiController@deviceUidUpdatePincode');


});
