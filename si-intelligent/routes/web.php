<?php

use Illuminate\Support\Facades\Route;

// Toutes ces vues sont protégées côté client (voir layouts/app.blade.php :
// redirection vers /login si aucun token n'est présent dans le navigateur).
// L'authentification réelle et les autorisations sont vérifiées par l'API
// (routes/api.php) à chaque appel — cette protection côté vue n'est qu'un
// confort de navigation, pas une frontière de sécurité.

Route::get('/login', fn () => view('login'))->name('login');

Route::get('/', fn () => view('dashboard'))->name('dashboard');
Route::get('/dashboard', fn () => view('dashboard'));

Route::get('/products', fn () => view('products'))->name('products.index');
Route::get('/sales', fn () => view('sales'))->name('sales.index');
Route::get('/purchases', fn () => view('purchases'))->name('purchases.index');
Route::get('/suppliers', fn () => view('suppliers'))->name('suppliers.index');
Route::get('/customers', fn () => view('customers'))->name('customers.index');
Route::get('/alerts', fn () => view('alerts'))->name('alerts.index');
Route::get('/finance', fn () => view('finance'))->name('finance.index');
Route::get('/quotes', fn () => view('quotes'))->name('quotes.index');
Route::get('/returns', fn () => view('returns'))->name('returns.index');
Route::get('/warehouses', fn () => view('warehouses'))->name('warehouses.index');
Route::get('/accounting', fn () => view('accounting'))->name('accounting.index');
Route::get('/hr', fn () => view('hr'))->name('hr.index');
Route::get('/forecast', fn () => view('forecast'))->name('forecast.index');
Route::get('/ai-hub', fn () => view('ai-hub'))->name('ai-hub.index');
Route::get('/assistant', fn () => view('assistant'))->name('assistant.index');
Route::get('/marketing', fn () => view('marketing'))->name('marketing.index');
Route::get('/attendance', fn () => view('attendance'))->name('attendance.index');

// Pages "espace" : regroupent les modules d'un même domaine (voir config/navigation.php).
Route::get('/espace/{section}', function (string $section) {
    abort_unless(array_key_exists($section, config('navigation.sections')), 404);

    return view('hub', ['sectionKey' => $section]);
})->name('hub');
