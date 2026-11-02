<?php

use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\RangeController;
use App\Http\Controllers\Admin\ShootingDayController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('einladung/{user}/{fingerprint}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('einladung/{user}/{fingerprint}', [InvitationController::class, 'store'])->name('invitation.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::middleware('admin')->prefix('verwaltung')->name('admin.')->group(function () {
        Route::get('mitglieder', [UserController::class, 'index'])->name('users.index');
        Route::get('mitglieder/neu', [UserController::class, 'create'])->name('users.create');
        Route::post('mitglieder', [UserController::class, 'store'])->name('users.store');
        Route::get('mitglieder/{user}', [UserController::class, 'edit'])->name('users.edit');
        Route::put('mitglieder/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('mitglieder/{user}/einladung', [UserController::class, 'invite'])->name('users.invite');

        Route::get('gruppen', [GroupController::class, 'index'])->name('groups.index');
        Route::post('gruppen', [GroupController::class, 'store'])->name('groups.store');
        Route::put('gruppen/{group}', [GroupController::class, 'update'])->name('groups.update');
        Route::delete('gruppen/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');

        Route::get('staende', [RangeController::class, 'index'])->name('ranges.index');
        Route::post('staende', [RangeController::class, 'store'])->name('ranges.store');
        Route::put('staende/{range}', [RangeController::class, 'update'])->name('ranges.update');

        Route::get('schiesstage', [ShootingDayController::class, 'index'])->name('shooting-days.index');
        Route::get('schiesstage/neu', [ShootingDayController::class, 'create'])->name('shooting-days.create');
        Route::post('schiesstage', [ShootingDayController::class, 'store'])->name('shooting-days.store');
        Route::get('schiesstage/{shootingDay}', [ShootingDayController::class, 'edit'])->name('shooting-days.edit');
        Route::put('schiesstage/{shootingDay}', [ShootingDayController::class, 'update'])->name('shooting-days.update');
        Route::post('schiesstage/{shootingDay}/absagen', [ShootingDayController::class, 'cancel'])->name('shooting-days.cancel');
        Route::post('schiesstage/{shootingDay}/oeffnen', [ShootingDayController::class, 'reopen'])->name('shooting-days.reopen');
    });
});

require __DIR__.'/settings.php';
