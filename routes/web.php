<?php

use App\Actions\Fortify\CreateNewUser;
use App\Domain\Retention;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\HelpRequestController as AdminHelpRequestController;
use App\Http\Controllers\Admin\KioskPinController as AdminKioskPinController;
use App\Http\Controllers\Admin\MemberImportController;
use App\Http\Controllers\Admin\RangeController;
use App\Http\Controllers\Admin\RecordController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShootingDayController;
use App\Http\Controllers\Admin\ShootingDayImportController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApplicationEmailController;
use App\Http\Controllers\ArrivalController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpRequestController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\RangeOfficeController;
use App\Http\Controllers\ShootingDayController as MemberShootingDayController;
use App\Http\Controllers\ShootingRecordController;
use App\Http\Controllers\SupervisionController;
use App\Http\Controllers\TodayController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;

// Startseite für Gäste; angemeldete Nutzer gehen direkt zur Übersicht.
Route::get('/', function (Request $request) {
    if ($request->user()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'address' => config('verein.address'),
        'canRegister' => CreateNewUser::open(),
    ]);
})->name('home');

// Fortifys Passwortbestätigung mit Drossel (E3-Hürde vor Storno, Korrektur, Nachtrag, Kiosk-PIN).
// Ersetzt die gleichnamige Fortify-Route; sonst unverändert (FortifyServiceProvider, Limiter password-confirm).
Route::post('user/confirm-password', [ConfirmablePasswordController::class, 'store'])
    ->middleware(['auth:'.config('fortify.guard'), 'throttle:password-confirm'])
    ->name('password.confirm.store');

Route::get('datenschutz', fn () => Inertia::render('Privacy', [
    'club' => config('verein.name'),
    'address' => config('verein.address'),
    'email' => config('verein.contact_email'),
    'recordYears' => Retention::recordYears(),
    'guestMonths' => Retention::guestMonths(),
]))->name('privacy');

Route::get('impressum', function () {
    abort_if(blank(config('verein.imprint')), 404);

    return Inertia::render('Imprint', ['text' => config('verein.imprint')]);
})->name('imprint');

// „Report Bug / Request Feature“ auf jeder Seite: für Gäste (Startseite, Anmeldung, Kiosk) und Angemeldete.
Route::post('hilfe', [HelpRequestController::class, 'store'])->middleware('throttle:help-requests')->name('help-requests.store');

Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('antrag/bestaetigen/{user}/{hash}', ApplicationEmailController::class)->middleware('throttle:6,1')->name('application.confirm-email');
    Route::get('einladung/{user}/{fingerprint}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('einladung/{user}/{fingerprint}', [InvitationController::class, 'store'])->name('invitation.store');
});

// Kiosk über den Session-Link: ohne Anmeldung, angemeldete Nutzer werden umgeleitet (E2).
Route::middleware(['guest', 'throttle:60,1'])->group(function () {
    Route::get('kiosk/{token}', [KioskController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->name('kiosk.show');
    Route::post('kiosk/{token}/pin', [KioskController::class, 'verify'])->where('token', '[A-Za-z0-9]{48}')->name('kiosk.verify');
    Route::post('kiosk/{token}/eintrag', [KioskController::class, 'store'])->where('token', '[A-Za-z0-9]{48}')->name('kiosk.store');
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

    // Erfassen am eigenen Gerät; einziger Einstieg ist die Karte „Heute schießen“ auf dem Dashboard.
    Route::get('heute', [TodayController::class, 'show'])->name('today.show');
    Route::post('heute/eintrag', [TodayController::class, 'store'])->name('today.store');
    Route::post('heute/eintrag/{entry}/zurueckziehen', [TodayController::class, 'withdraw'])->name('today.withdraw');

    Route::get('stand', [RangeOfficeController::class, 'show'])->name('range-office.show');
    Route::get('stand/{shootingDay}/da/{code}', ArrivalController::class)->where('code', '[a-f0-9]{12}')->name('range-office.arrive');
    Route::post('stand/eintragen', [RangeOfficeController::class, 'enter'])->name('range-office.enter');
    Route::post('stand/session', [RangeOfficeController::class, 'startSession'])->name('range-office.session.start');
    Route::post('stand/session/{shootingSession}/mitglied', [RangeOfficeController::class, 'addMember'])->name('range-office.session.member');
    Route::post('stand/session/{shootingSession}/gast', [RangeOfficeController::class, 'addGuest'])->name('range-office.session.guest');
    Route::post('stand/session/{shootingSession}/kiosk', [RangeOfficeController::class, 'kioskLink'])->name('range-office.session.kiosk');
    Route::post('stand/session/{shootingSession}/kiosk-hier', [RangeOfficeController::class, 'kioskHere'])->name('range-office.session.kiosk-here');
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
        // Import vor mitglieder/{user}, sonst fängt die Modellbindung „import“ ab
        Route::get('mitglieder/import', [MemberImportController::class, 'create'])->name('users.import');
        Route::get('mitglieder/import/vorlage.csv', [MemberImportController::class, 'template'])->name('users.import-template');
        Route::post('mitglieder/import/pruefen', [MemberImportController::class, 'preview'])->name('users.import-preview');
        Route::post('mitglieder/import', [MemberImportController::class, 'store'])->name('users.import-store');
        Route::delete('mitglieder/import', [MemberImportController::class, 'cancel'])->name('users.import-cancel');
        Route::get('mitglieder/{user}', [UserController::class, 'edit'])->name('users.edit');
        Route::put('mitglieder/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('mitglieder/{user}/einladung', [UserController::class, 'invite'])->name('users.invite');
        Route::post('mitglieder/{user}/aufnehmen', [UserController::class, 'admit'])->name('users.admit');
        Route::delete('mitglieder/{user}/antrag', [UserController::class, 'reject'])->name('users.reject');
        Route::delete('mitglieder/{user}/kiosk-pin', [AdminKioskPinController::class, 'destroy'])->name('users.kiosk-pin.destroy');
        Route::post('mitglieder/{user}/aufsichtsregister', [UserController::class, 'register'])->name('users.register');

        Route::get('protokoll', [AuditController::class, 'index'])->name('audit.index');

        Route::get('rueckmeldungen', [AdminHelpRequestController::class, 'index'])->name('help-requests.index');
        Route::get('rueckmeldungen/{helpRequest}', [AdminHelpRequestController::class, 'show'])->name('help-requests.show');
        Route::get('rueckmeldungen/{helpRequest}/screenshot.jpg', [AdminHelpRequestController::class, 'screenshot'])->name('help-requests.screenshot');
        Route::put('rueckmeldungen/{helpRequest}', [AdminHelpRequestController::class, 'update'])->name('help-requests.update');
        Route::delete('rueckmeldungen/{helpRequest}', [AdminHelpRequestController::class, 'destroy'])->name('help-requests.destroy');
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
        // Import vor schiesstage/{shootingDay}, sonst fängt die Modellbindung „import“ ab
        Route::get('schiesstage/import', [ShootingDayImportController::class, 'create'])->name('shooting-days.import');
        Route::get('schiesstage/import/vorlage.csv', [ShootingDayImportController::class, 'template'])->name('shooting-days.import-template');
        Route::post('schiesstage/import/pruefen', [ShootingDayImportController::class, 'preview'])->name('shooting-days.import-preview');
        Route::post('schiesstage/import', [ShootingDayImportController::class, 'store'])->name('shooting-days.import-store');
        Route::delete('schiesstage/import', [ShootingDayImportController::class, 'cancel'])->name('shooting-days.import-cancel');
        Route::get('schiesstage/{shootingDay}', [ShootingDayController::class, 'edit'])->name('shooting-days.edit');
        Route::put('schiesstage/{shootingDay}', [ShootingDayController::class, 'update'])->name('shooting-days.update');
        Route::post('schiesstage/{shootingDay}/absagen', [ShootingDayController::class, 'cancel'])->name('shooting-days.cancel');
        Route::post('schiesstage/{shootingDay}/oeffnen', [ShootingDayController::class, 'reopen'])->name('shooting-days.reopen');
        Route::delete('schiesstage/{shootingDay}', [ShootingDayController::class, 'destroy'])->name('shooting-days.destroy');
    });
});

require __DIR__.'/settings.php';
