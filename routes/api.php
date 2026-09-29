<?php

use App\Http\Controllers\Public\AdoptableAnimalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/adoptable', [AdoptableAnimalController::class, 'apiIndex'])->name('api.v1.adoptable.index');
    Route::get('/adoptable/{animal}', [AdoptableAnimalController::class, 'apiShow'])->name('api.v1.adoptable.show');
});
