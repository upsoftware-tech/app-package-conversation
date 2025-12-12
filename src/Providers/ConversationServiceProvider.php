<?php

namespace Upsoftware\Conversation\Providers;

use Upsoftware\Core\Providers\BaseServiceProvider;
use Illuminate\Support\Facades\Route;
use Upsoftware\Conversation\Http\Controllers\ConversationController;
use Upsoftware\Conversation\Http\Controllers\ConversationGroupController;
use Upsoftware\Conversation\Http\Controllers\ConversationMessageController;

class ConversationServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        
        $this->loadMigrationsFrom(__DIR__ . '/../Database/migrations');

        Route::middleware('api')
            ->prefix('conversation')
            ->group(function () {
                Route::get('/', [ConversationController::class, 'index']);
                Route::post('/', [ConversationController::class, 'store']);
                Route::get('/{conversation}', [ConversationController::class, 'show']);
                Route::post('/{conversation}/messages', [ConversationMessageController::class, 'store']);

                Route::apiResource('groups', ConversationGroupController::class);
            });
    }
}
