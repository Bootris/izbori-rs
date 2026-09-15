<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Public site = one static React SPA that reads /data/*.json (see
| docs/DATA-CONTRACT.md). It never talks to Laravel. Everything that is not the
| admin panel, the data folder or a real file falls through to the SPA shell.
*/
$reserved = implode('|', array_map('preg_quote', array_filter([
    config('izbori.admin_path'),
    'data', 'storage', 'build', 'up', 'livewire', 'filament', 'js', 'css', 'fonts',
])));

Route::get('/{any?}', fn () => view('app'))
    ->where('any', "^(?!(?:{$reserved})(?:/|$)).*$")
    ->name('spa');
