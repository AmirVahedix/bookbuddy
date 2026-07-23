<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\SummaryController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [BookController::class, 'dashboard'])->name('dashboard');
    Route::get('/summaries', [SummaryController::class, 'index'])->name('summaries.index');
    Route::get('/books', [BookController::class, 'index'])->name('books.index');
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::get('/books/{book}/read', [BookController::class, 'read'])->name('books.read');
    Route::get('/books/{book}/summaries/{summary?}', [BookController::class, 'summaries'])->name('books.summaries');
    Route::patch('/books/{book}/progress', [BookController::class, 'updateProgress'])->name('books.update-progress');
    Route::post('/books/{book}/summarize', [BookController::class, 'summarize'])->name('books.summarize');
    Route::patch('/books/{book}/sections/{section}/toggle-read', [BookController::class, 'toggleSectionRead'])->name('books.sections.toggle-read');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
    Route::post('/summaries/{summary}/chat', [SummaryController::class, 'chat'])->name('summaries.chat');
    Route::delete('/summaries/{summary}/chat', [SummaryController::class, 'clearChat'])->name('summaries.clear-chat');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');

    return 'ok';
});

Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');

    return 'ok';
});
