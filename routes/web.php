<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\MemberImportController;
use App\Http\Controllers\Admin\RangeController;
use App\Http\Controllers\Admin\RecordController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShootingDayController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ArrivalController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\RangeOfficeController;
use App\Http\Controllers\ShootingDayController as MemberShootingDayController;
use App\Http\Controllers\ShootingRecordController;
use App\Http\Controllers\SupervisionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('einladung/{user}/{fingerprint}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('einladung/{user}/{fingerprint}', [InvitationController::class, 'store'])->name('invitation.store');
});

Route::get('kalender/{token}.ics', [CalendarController::class, 'feed'])
    ->middleware('throttle:60,1')
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('calendar.feed');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('schiesstage', [MemberShootingDayController::class, 'index'])->name('shooting-days.index');
    Route::get('schiesstage/{shootingDay}', [MemberShootingDayController::class, 'show'])->name('shooting-days.show');
    Route::post('schiesstage/{shootingDay}/buchungen', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('schiesstage/{shootingDay}/aufsicht', [SupervisionController::class, 'store'])->name('supervisions.store');
    Route::delete('aufsicht/{supervision}', [SupervisionController::class, 'destroy'])->name('supervisions.destroy');
    Route::get('buchungen', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('kalender/neu', [CalendarController::class, 'regenerate'])->name('calendar.regenerate');

    Route::get('schiesskladde', [ShootingRecordController::class, 'index'])->name('record.index');
    Route::get('schiesskladde/pdf', [ShootingRecordController::class, 'pdf'])->name('record.pdf');

    Route::get('stand', [RangeOfficeController::class, 'show'])->name('range-office.show');
    Route::get('stand/{shootingDay}/da/{code}', ArrivalController::class)->where('code', '[a-f0-9]{12}')->name('range-office.arrive');
    Route::post('stand/{shootingDay}/eintragen', [RangeOfficeController::class, 'walkIn'])->name('range-office.walk-in');
    Route::post('stand/buchung/{booking}/anwesend', [RangeOfficeController::class, 'checkIn'])->name('range-office.check-in');
    Route::post('stand/buchung/{booking}/nicht-erschienen', [RangeOfficeController::class, 'noShow'])->name('range-office.no-show');
    Route::post('stand/buchung/{booking}/zuruecksetzen', [RangeOfficeController::class, 'reset'])->name('range-office.reset');
    Route::delete('buchungen/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

    Route::middleware('admin')->prefix('verwaltung')->name('admin.')->group(function () {
        Route::get('mitglieder', [UserController::class, 'index'])->name('users.index');
        Route::get('mitglieder/neu', [UserController::class, 'create'])->name('users.create');
        Route::get('mitglieder/import', [MemberImportController::class, 'create'])->name('users.import');
        Route::get('mitglieder/import/vorlage.csv', [MemberImportController::class, 'template'])->name('users.import-template');
        Route::post('mitglieder/import/pruefen', [MemberImportController::class, 'preview'])->name('users.import-preview');
        Route::post('mitglieder/import', [MemberImportController::class, 'store'])->name('users.import-store');
        Route::delete('mitglieder/import', [MemberImportController::class, 'cancel'])->name('users.import-cancel');
        Route::post('mitglieder', [UserController::class, 'store'])->name('users.store');
        Route::get('mitglieder/{user}', [UserController::class, 'edit'])->name('users.edit');
        Route::put('mitglieder/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('mitglieder/{user}/einladung', [UserController::class, 'invite'])->name('users.invite');

        Route::get('gruppen', [GroupController::class, 'index'])->name('groups.index');
        Route::post('gruppen', [GroupController::class, 'store'])->name('groups.store');
        Route::put('gruppen/{group}', [GroupController::class, 'update'])->name('groups.update');
        Route::delete('gruppen/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');

        Route::get('protokoll', [AuditController::class, 'index'])->name('audit.index');
        Route::get('statistik', StatisticsController::class)->name('statistics');

        Route::get('einstellungen', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('einstellungen', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('einstellungen/testmail', [SettingsController::class, 'testMail'])->middleware('throttle:3,1')->name('settings.test-mail');

        Route::get('schiesskladde', [RecordController::class, 'index'])->name('record.index');
        Route::get('mitglieder/{user}/schiesskladde.pdf', [RecordController::class, 'memberPdf'])->name('record.member-pdf');
        Route::get('mitglieder/{user}/mitgliedschaft.pdf', [RecordController::class, 'certificate'])->name('record.certificate');

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
        Route::delete('schiesstage/{shootingDay}', [ShootingDayController::class, 'destroy'])->name('shooting-days.destroy');
    });
});

require __DIR__.'/settings.php';
