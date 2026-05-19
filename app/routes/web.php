<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\QuickEntryController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SendController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemplatesController;
use App\Http\Controllers\CampaignsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/quick-entry', [QuickEntryController::class, 'index'])->name('quick-entry');

Route::get('/send', [SendController::class, 'form'])->name('send.form');
Route::post('/halt', [SendController::class, 'halt'])->name('halt');

Route::get('/templates', [TemplatesController::class, 'index'])->name('templates.index');
Route::get('/campaigns', [CampaignsController::class, 'index'])->name('campaigns.index');
Route::get('/campaigns/{campaign}', [CampaignsController::class, 'show'])->name('campaigns.show');

Route::get('/reports', [ReportsController::class, 'index'])->name('reports');
Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('/reminders/trigger', [SettingsController::class, 'triggerReminders'])->name('reminders.trigger');
Route::post('/settings/test-bulkgate', [SettingsController::class, 'testBulkGate'])->name('settings.test_bulkgate');

Route::get('/import', [ImportController::class, 'form'])->name('import.form');
Route::post('/import', [ImportController::class, 'run'])->name('import.run');
