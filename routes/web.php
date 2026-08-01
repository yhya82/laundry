<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Phase 1 verification only — proves Livewire renders and Tailwind picks up
// classes used inside a Livewire view, not just welcome.blade.php. Remove
// once Phase 3 replaces this with the real app shell.
Route::get('/phase1-smoke-test', function () {
    return view('phase-1-smoke-test');
});
