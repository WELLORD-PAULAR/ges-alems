<?php

use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EnrollmentLookupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')
    ->name('home');

/*
|--------------------------------------------------------------------------
| Authentication redirect
|--------------------------------------------------------------------------
|
| Fortify redirects authenticated users to /dashboard.
| This route then sends them to the correct dashboard.
|
*/

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();

    return redirect(
        $user->role->homePath()
    );
})
    ->middleware('auth')
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'role:admin',
])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', function () {
            return Inertia::render('admin/Dashboard');
        })
            ->name('dashboard');

        require __DIR__.'/admin.php';
    });

/*
|--------------------------------------------------------------------------
| Teacher routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'role:teacher',
])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {

        Route::get('/dashboard', function () {
            return Inertia::render('teacher/Dashboard');
        })
            ->name('dashboard');
    });

require __DIR__.'/settings.php';

/*
|--------------------------------------------------------------------------
| Public enrollment
|--------------------------------------------------------------------------
*/

Route::get('/enroll', [EnrollmentController::class, 'create'])
    ->name('enrollment.create');

Route::post('/enroll', [EnrollmentController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('enrollment.store');

Route::get('/enroll/success', [EnrollmentController::class, 'success'])
    ->name('enrollment.success');

Route::get('/lookups/{type}', [EnrollmentLookupController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('enrollment.lookups.show');
