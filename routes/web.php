<?php

use App\Http\Controllers\FormController;
use App\Http\Controllers\PublicSubmissionController;
use App\Livewire\Builder\Builder;
use App\Livewire\Forms\Create;
use App\Livewire\Forms\Edit;
use App\Livewire\Forms\GenerateForm;
use App\Livewire\Forms\Import;
use App\Livewire\Forms\Index;
use App\Livewire\Forms\Show;
use App\Livewire\Public\FormRenderer;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth')->group(function (): void {
    Route::get('/forms', Index::class)->name('forms.index');
    Route::get('/forms/create', Create::class)->name('forms.create');
    Route::get('/forms/import', Import::class)->name('forms.import');
    Route::get('/forms/ai', GenerateForm::class)->name('forms.ai');
    Route::post('/forms', [FormController::class, 'store'])->name('forms.store');
    Route::get('/forms/{form}', Show::class)->name('forms.show');
    Route::get('/forms/{form}/edit', Edit::class)->name('forms.edit');
    Route::put('/forms/{form}', [FormController::class, 'update'])->name('forms.update');
    Route::delete('/forms/{form}', [FormController::class, 'destroy'])->name('forms.destroy');
    Route::get('/forms/{form}/builder', Builder::class)->name('forms.builder');
    Route::post('/forms/{form}/publish', [FormController::class, 'publish'])->name('forms.publish');
    Route::post('/forms/{form}/archive', [FormController::class, 'archive'])->name('forms.archive');
});

Route::get('/f/{uuid}', FormRenderer::class)->name('public.forms.show');
Route::post('/f/{uuid}/submit', [PublicSubmissionController::class, 'store'])->name('public.forms.submit');

Route::view('dashboard', 'dashboard')->middleware(['auth', 'verified'])->name('dashboard');
Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

require __DIR__.'/auth.php';
