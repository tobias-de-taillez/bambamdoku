<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\RangeController;
use App\Http\Controllers\Admin\RecordController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShootingDayController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ArrivalController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\RangeOfficeController;
use App\Http\Controllers\ShootingDayController as MemberShootingDayController;
use App\Http\Controllers\ShootingRecordController;
use App\Http\Controllers\SupervisionController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

Route::get('datenschutz', fn () => Inertia::render('Privacy', [
    'club' => config('verein.name'),
    'address' => config('verein.address'),
]))->name('privacy');

Route::get('impressum', function () {
    abort_if(blank(config('verein.imprint')), 404);

    return Inertia::render('Imprint', ['text' => config('verein.imprint')]);
})->name('imprint');

Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('einladung/{user}/{fingerprint}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('einladung/{user}/{fingerprint}', [InvitationController::class, 'store'])->name('invitation.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::inertia('anleitung', 'Help')->name('help');

    Route::get('schiesstage', [MemberShootingDayController::class, 'index'])->name('shooting-days.index');
    Route::get('schiesstage/{shootingDay}', [MemberShootingDayController::class, 'show'])->name('shooting-days.show');
    Route::post('schiesstage/{shootingDay}/buchungen', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('schiesstage/{shootingDay}/aufsicht', [SupervisionController::class, 'store'])->name('supervisions.store');
    Route::delete('aufsicht/{supervision}', [SupervisionController::class, 'destroy'])->name('supervisions.destroy');
    Route::get('buchungen', [BookingController::class, 'index'])->name('bookings.index');

    Route::get('schiesskladde', [ShootingRecordController::class, 'index'])->name('record.index');
    Route::get('schiesskladde/pdf', [ShootingRecordController::class, 'pdf'])->name('record.pdf');
    Route::get('schiesskladde/teilnahmenachweis.pdf', [ShootingRecordController::class, 'disciplinePdf'])->name('record.discipline-pdf');

    Route::get('stand', [RangeOfficeController::class, 'show'])->name('range-office.show');
    Route::get('stand/{shootingDay}/da/{code}', ArrivalController::class)->where('code', '[a-f0-9]{12}')->name('range-office.arrive');
    Route::post('stand/eintragen', [RangeOfficeController::class, 'enter'])->name('range-office.enter');
    Route::post('stand/session', [RangeOfficeController::class, 'startSession'])->name('range-office.session.start');
    Route::post('stand/session/{shootingSession}/mitglied', [RangeOfficeController::class, 'addMember'])->name('range-office.session.member');
    Route::post('stand/session/{shootingSession}/gast', [RangeOfficeController::class, 'addGuest'])->name('range-office.session.guest');
    Route::post('stand/session/{shootingSession}/abzeichnen', [RangeOfficeController::class, 'signOff'])->name('range-office.session.sign-off');
    Route::post('stand/session/teilnehmer/{participant}/entfernen', [RangeOfficeController::class, 'removeParticipant'])->name('range-office.session.remove');
    Route::post('stand/session/teilnehmer/{participant}/eintrag', [RangeOfficeController::class, 'stage'])->name('range-office.session.stage');
    Route::post('stand/session/eintrag/{entry}/zurueckziehen', [RangeOfficeController::class, 'withdraw'])->name('range-office.session.withdraw');
    Route::post('stand/session/eintrag/{entry}/ablehnen', [RangeOfficeController::class, 'reject'])->name('range-office.session.reject');
    Route::post('stand/buchung/{booking}/anwesend', [RangeOfficeController::class, 'checkIn'])->name('range-office.check-in');
    Route::post('stand/buchung/{booking}/nicht-erschienen', [RangeOfficeController::class, 'noShow'])->name('range-office.no-show');
    Route::post('stand/buchung/{booking}/zuruecksetzen', [RangeOfficeController::class, 'reset'])->name('range-office.reset');
    Route::delete('buchungen/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

    Route::middleware('admin')->prefix('verwaltung')->name('admin.')->group(function () {
        Route::get('mitglieder', [UserController::class, 'index'])->name('users.index');
        Route::get('mitglieder/neu', [UserController::class, 'create'])->name('users.create');
        Route::post('mitglieder', [UserController::class, 'store'])->name('users.store');
        Route::get('mitglieder/{user}', [UserController::class, 'edit'])->name('users.edit');
        Route::put('mitglieder/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('mitglieder/{user}/einladung', [UserController::class, 'invite'])->name('users.invite');
        Route::post('mitglieder/{user}/aufnehmen', [UserController::class, 'admit'])->name('users.admit');
        Route::delete('mitglieder/{user}/antrag', [UserController::class, 'reject'])->name('users.reject');
        Route::post('mitglieder/{user}/aufsichtsregister', [UserController::class, 'register'])->name('users.register');

        Route::get('protokoll', [AuditController::class, 'index'])->name('audit.index');
        Route::get('statistik', StatisticsController::class)->name('statistics');

        Route::get('einstellungen', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('einstellungen', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('einstellungen/testmail', [SettingsController::class, 'testMail'])->middleware('throttle:3,1')->name('settings.test-mail');
        Route::post('einstellungen/sicherung', [SettingsController::class, 'backup'])->middleware('throttle:3,1')->name('settings.backup');
        Route::get('einstellungen/sicherung/{name}', [SettingsController::class, 'downloadBackup'])->name('settings.backup-download');

        Route::get('schiesskladde', [RecordController::class, 'index'])->name('record.index');
        // Nachtrag, Storno und Korrektur nur nach erneuter Passworteingabe (E3); vorab bestätigen, damit kein Formular verloren geht
        Route::get('schiesskladde/passwort', [RecordController::class, 'confirmed'])->middleware(RequirePassword::class)->name('record.confirm');
        Route::post('schiesskladde/nachtrag', [RecordController::class, 'storeLate'])->middleware(RequirePassword::class)->name('record.late');
        Route::get('schiesskladde/papier-ersatzliste.pdf', [RecordController::class, 'paperList'])->name('record.paper-list');
        Route::post('schiesskladde/{entry}/storno', [RecordController::class, 'cancel'])->middleware(RequirePassword::class)->name('record.cancel');
        Route::post('schiesskladde/{entry}/korrektur', [RecordController::class, 'correct'])->middleware(RequirePassword::class)->name('record.correct');
        Route::get('schiesskladde/session/{shootingSession}/protokoll.pdf', [RecordController::class, 'sessionPdf'])->name('record.session-pdf');
        Route::get('mitglieder/{user}/schiesskladde.pdf', [RecordController::class, 'memberPdf'])->name('record.member-pdf');
        Route::get('mitglieder/{user}/teilnahmenachweis.pdf', [RecordController::class, 'memberDisciplinePdf'])->name('record.member-discipline-pdf');
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
