<?php

use App\Http\Controllers\Api\FieldController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\PublicFormController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => '1.0.0',
            'application' => 'AI Form Builder',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::get('/public/forms/{uuid}', [PublicFormController::class, 'show']);
    Route::post('/public/forms/{uuid}/submit', [PublicFormController::class, 'submit'])
        ->middleware('throttle:public-submit');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/forms/generate', [FormController::class, 'generate'])
            ->middleware('throttle:ai-generate');
        Route::post('/forms/import', [FormController::class, 'import'])
            ->middleware('throttle:form-import');
        Route::get('/forms', [FormController::class, 'index']);
        Route::post('/forms', [FormController::class, 'store']);
        Route::get('/forms/{form}', [FormController::class, 'show']);
        Route::put('/forms/{form}', [FormController::class, 'update']);
        Route::delete('/forms/{form}', [FormController::class, 'destroy']);
        Route::post('/forms/{form}/publish', [FormController::class, 'publish']);
        Route::post('/forms/{form}/archive', [FormController::class, 'archive']);
        Route::post('/forms/{form}/fields', [FieldController::class, 'store']);
        Route::post('/forms/{form}/reorder-fields', [FieldController::class, 'reorder']);
        Route::get('/forms/{form}/submissions', [SubmissionController::class, 'index']);
        Route::put('/fields/{field}', [FieldController::class, 'update']);
        Route::delete('/fields/{field}', [FieldController::class, 'destroy']);
        Route::post('/fields/{field}/duplicate', [FieldController::class, 'duplicate']);
        Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
        Route::delete('/submissions/{submission}', [SubmissionController::class, 'destroy']);
    });
});
