<?php

use App\Http\Controllers\Admin\SubjectController;
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
