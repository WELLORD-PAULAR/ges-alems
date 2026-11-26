<?php

use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SectioningController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin module routes (Lane C)
|--------------------------------------------------------------------------
|
| Required from inside the `auth` + `role:admin` group in routes/web.php, so
| every route here is already prefixed with /admin and named `admin.*`.
|
*/

Route::resource('subjects', SubjectController::class)
    ->only(['index', 'store', 'update', 'destroy']);

Route::resource('teachers', TeacherController::class)
    ->only(['index', 'store', 'update', 'destroy']);

Route::resource('sections', SectionController::class)
    ->only(['index', 'store', 'update', 'destroy']);

Route::get('sectioning', [SectioningController::class, 'index'])->name('sectioning.index');
Route::post('sectioning/bulk', [SectioningController::class, 'bulk'])->name('sectioning.bulk');
Route::put('sectioning/{enrollment}', [SectioningController::class, 'assign'])->name('sectioning.assign');
