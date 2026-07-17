<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('permission:students.view');

    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:students.view');
    Route::post('/students', [StudentController::class, 'store'])->middleware('permission:students.create');
    Route::get('/students/{student}', [StudentController::class, 'show'])->middleware('permission:students.view');
    Route::put('/students/{student}', [StudentController::class, 'update'])->middleware('permission:students.update');
    Route::patch('/students/{student}', [StudentController::class, 'update'])->middleware('permission:students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->middleware('permission:students.delete');

    Route::get('/enrollments', [EnrollmentController::class, 'index'])->middleware('permission:enrollments.view');
    Route::post('/enrollments', [EnrollmentController::class, 'store'])->middleware('permission:enrollments.create');

    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:payments.create');
    Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])
        ->middleware('permission:payments.cancel');
});
