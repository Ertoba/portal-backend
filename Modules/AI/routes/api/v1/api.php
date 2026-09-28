<?php

use Illuminate\Support\Facades\Route;

use Modules\AI\app\Http\Controllers\Api\ProductAutoFillController;
use Modules\AI\app\Http\Controllers\Api\V1\AiChatController;
use Modules\AI\app\Http\Middleware\AiChatEnabled;

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

Route::group(['prefix' => 'ai', 'as' => 'ai.','middleware'=>['vendor.api','mili.feature:vendor_app']], function () {
    // Route::get('generate-food-data', [ProductAutoFillController::class, 'getData']);
    Route::get('generate-title-and-description', [ProductAutoFillController::class, 'getTitleAndDescription']);
    Route::get('generate-other-data', [ProductAutoFillController::class, 'getOtherData']);
    Route::get('generate-variation-data', [ProductAutoFillController::class, 'getVariationData']);
    Route::get('generate-title-suggestions', [ProductAutoFillController::class, 'generateTitleSuggestions']);
    Route::post('generate-form-image', [ProductAutoFillController::class, 'analyzeImageAutoFill']);
});

// AI Chatbot — supports BOTH authenticated customers AND guests
// Authenticated: uses auth('api')->user() — chat is scoped to user_id
// Guest: must pass guest_id header or body param — chat is scoped to guest_id
Route::group(['prefix' => 'customer'], function () {
    Route::group([
        'prefix'     => 'ai-chat',
        // These endpoints are PUBLIC (guests allowed) and `send` triggers a paid
        // LLM call, so they must be rate-limited to prevent cost abuse / DoS.
        // throttle keys by auth user, else by IP. The group cap covers the cheap
        // read endpoints; `send` gets a tighter per-minute cap below.
        'middleware' => [AiChatEnabled::class, 'throttle:30,1'],
    ], function () {
        Route::post('send', [AiChatController::class, 'send'])->middleware('throttle:12,1');
        Route::get('conversations', [AiChatController::class, 'conversations']);
        Route::get('messages', [AiChatController::class, 'messages']);
        Route::delete('conversations/{id}', [AiChatController::class, 'deleteConversation']);
    });
});
