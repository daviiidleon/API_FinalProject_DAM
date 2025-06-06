<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController; // Nuevo
use App\Http\Controllers\TransactionController; // Nuevo
use App\Http\Controllers\BudgetController; // Nuevo
use App\Http\Controllers\SavingsGoalController; // Nuevo

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas de autenticación (no protegidas por Sanctum)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas por Sanctum (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']); // Solo para obtener el usuario autenticado

    // Rutas del perfil de usuario
    Route::get('/user/profile', [UserProfileController::class, 'show']);
    Route::post('/user/profile', [UserProfileController::class, 'update']); // Usar POST para update
    Route::post('/user/avatar', [UserProfileController::class, 'storeAvatar']);

    // Rutas de cuentas (usando apiResource para la conveniencia de CRUD)
    Route::apiResource('accounts', AccountController::class);

    // Rutas de categorías (nuevas)
    Route::apiResource('categories', CategoryController::class);

    // Rutas de transacciones (nuevas)
    Route::apiResource('transactions', TransactionController::class);
    // Para recalcular spent_amount de un presupuesto (opcional)
    Route::post('/budgets/{budget}/recalculate-spent', [BudgetController::class, 'recalculateSpentAmount']);

    // Rutas de presupuestos (nuevas)
    Route::apiResource('budgets', BudgetController::class);

    // Rutas de metas de ahorro (nuevas)
    Route::apiResource('savings-goals', SavingsGoalController::class);
    Route::post('/savings-goals/{savings_goal}/add-funds', [SavingsGoalController::class, 'addFunds']); // Para añadir fondos a una meta

    // Rutas para Dashboard, Predicciones y Reportes (más adelante)
    // Route::get('/dashboard', [DashboardController::class, 'index']);
    // Route::get('/predictions', [PredictionController::class, 'index']);
    // Route::get('/reports', [ReportController::class, 'index']);
});