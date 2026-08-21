<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Typdy\StarterKit\Laravel\Controllers\WebhooksController;

Route::post('/typdy/webhooks/{name}', WebhooksController::class)->name('typdy.webhooks');
