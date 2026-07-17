<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateWebController;
use App\Http\Controllers\EnrollmentWebController;
use App\Http\Controllers\PaymentWebController;
use App\Http\Controllers\SchoolDashboardController;
use App\Http\Controllers\SchoolClassWebController;
use App\Http\Controllers\SchoolSettingWebController;
use App\Http\Controllers\StaffUserWebController;
use App\Http\Controllers\StudentWebController;
use App\Http\Controllers\TariffWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/dashboard', SchoolDashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::get('/settings', [SchoolSettingWebController::class, 'edit'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.edit');

Route::put('/settings', [SchoolSettingWebController::class, 'update'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.update');

Route::resource('staff', StaffUserWebController::class)
    ->middleware(['auth', 'permission:users.manage'])
    ->parameters(['staff' => 'user']);

Route::get('/students/{student}/registration-sheet', [StudentWebController::class, 'registrationSheet'])
    ->middleware('auth')
    ->name('students.registration-sheet');

Route::get('/students/{student}/registration-sheet/pdf', [StudentWebController::class, 'registrationSheetPdf'])
    ->middleware('auth')
    ->name('students.registration-sheet.pdf');

Route::post('/classes/{schoolClass}/students', [SchoolClassWebController::class, 'attachStudent'])
    ->middleware('auth')
    ->name('classes.students.attach');

Route::delete('/classes/{schoolClass}/students/{enrollment}', [SchoolClassWebController::class, 'detachStudent'])
    ->middleware('auth')
    ->name('classes.students.detach');

Route::resource('classes', SchoolClassWebController::class)
    ->middleware('auth')
    ->parameters(['classes' => 'schoolClass']);

Route::resource('enrollments', EnrollmentWebController::class)
    ->middleware('auth');

Route::get('/payments/unpaid', [PaymentWebController::class, 'unpaid'])
    ->middleware('auth')
    ->name('payments.unpaid');

Route::get('/payments/{payment}/receipt', [PaymentWebController::class, 'receipt'])
    ->middleware('auth')
    ->name('payments.receipt');

Route::resource('payments', PaymentWebController::class)
    ->middleware('auth')
    ->only(['index', 'create', 'store', 'show', 'destroy']);

Route::get('/tariffs', [TariffWebController::class, 'index'])
    ->middleware('auth')
    ->name('tariffs.index');

Route::post('/tariffs/defaults', [TariffWebController::class, 'applyDefaults'])
    ->middleware('auth')
    ->name('tariffs.defaults');

Route::get('/tariffs/classes/{schoolClass}/edit', [TariffWebController::class, 'edit'])
    ->middleware('auth')
    ->name('tariffs.edit');

Route::put('/tariffs/classes/{schoolClass}', [TariffWebController::class, 'update'])
    ->middleware('auth')
    ->name('tariffs.update');

Route::get('/certificates/{certificate}/pdf', [CertificateWebController::class, 'pdf'])
    ->middleware('auth')
    ->name('certificates.pdf');

Route::resource('certificates', CertificateWebController::class)
    ->middleware('auth')
    ->only(['index', 'create', 'store', 'show']);

Route::resource('students', StudentWebController::class)
    ->middleware('auth');
