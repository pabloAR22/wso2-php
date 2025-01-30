<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\UserStatusController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/create-user', [AuthController::class, 'createUser']);
Route::put('/reset-password', [AuthController::class, 'resetPasswordV2']);
Route::post('/code-otp', [AuthController::class, 'codeOtp']);
Route::get('/search-user', [AuthController::class, 'searchUserById']);
Route::post('/generate-otp', [OtpController::class, 'generateOtp']);
Route::get('/validate-user', [OtpController::class, 'validateUserExists']);
Route::post('/validateOtp', [OtpController::class, 'validateCodeOtp']);
Route::patch('/userBlock/{id}', [UserStatusController::class, 'blockUser']);
Route::patch('/userUnblock/{id}', [UserStatusController::class, 'unblockUser']);
Route::get('/getIdUser/{id}', [UserStatusController::class, 'getIdUser']);
Route::post('/massiveBlock', [UserStatusController::class, 'massiveBlock']);
Route::post('/massiveBlockFile', [UserStatusController::class, 'massiveBlockFile']);
