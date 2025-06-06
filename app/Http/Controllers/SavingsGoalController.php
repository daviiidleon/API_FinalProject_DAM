<?php

namespace App\Http\Controllers;

use App\Models\SavingsGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class SavingsGoalController extends Controller
{
    public function index(Request $request)
    {
        $savingsGoals = $request->user()->savingsGoals()->orderBy('name')->get();
        return response()->json($savingsGoals);
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'target_amount' => ['required', 'numeric', 'min:0.01'],
                'saved_amount' => ['numeric', 'min:0'], // Puede ser 0 al inicio
                'target_date' => ['nullable', 'date', 'after_or_equal:today'],
                'description' => ['nullable', 'string'],
                'is_achieved' => ['boolean'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al crear meta de ahorro: ", ['errors' => $e->errors()]);
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        }

        $savingsGoal = $request->user()->savingsGoals()->create($validatedData);

        return response()->json(['message' => 'Meta de ahorro creada exitosamente.', 'savings_goal' => $savingsGoal], 201);
    }

    public function show(SavingsGoal $savingsGoal)
    {
        if ($savingsGoal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado a esta meta de ahorro.'], 403);
        }
        return response()->json($savingsGoal);
    }

    public function update(Request $request, SavingsGoal $savingsGoal)
    {
        if ($savingsGoal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado para actualizar esta meta de ahorro.'], 403);
        }

        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'target_amount' => ['required', 'numeric', 'min:0.01'],
                'saved_amount' => ['numeric', 'min:0', 'lte:target_amount'], // saved_amount no puede ser mayor que target_amount
                'target_date' => ['nullable', 'date', 'after_or_equal:today'],
                'description' => ['nullable', 'string'],
                'is_achieved' => ['boolean'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al actualizar meta de ahorro: ", ['errors' => $e->errors()]);
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        }

        $savingsGoal->update($validatedData);

        // Opcional: Actualizar 'is_achieved' automáticamente si saved_amount alcanza target_amount
        if ($savingsGoal->saved_amount >= $savingsGoal->target_amount && !$savingsGoal->is_achieved) {
            $savingsGoal->is_achieved = true;
            $savingsGoal->save();
        } elseif ($savingsGoal->saved_amount < $savingsGoal->target_amount && $savingsGoal->is_achieved) {
            $savingsGoal->is_achieved = false;
            $savingsGoal->save();
        }

        return response()->json(['message' => 'Meta de ahorro actualizada exitosamente.', 'savings_goal' => $savingsGoal]);
    }

    public function destroy(SavingsGoal $savingsGoal)
    {
        if ($savingsGoal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado para eliminar esta meta de ahorro.'], 403);
        }

        $savingsGoal->delete();
        return response()->json(['message' => 'Meta de ahorro eliminada exitosamente.'], 204);
    }

    // Opcional: Añadir fondos a una meta de ahorro
    public function addFunds(Request $request, SavingsGoal $savingsGoal)
    {
        if ($savingsGoal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado para añadir fondos.'], 403);
        }

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['required', 'exists:accounts,id'], // Desde qué cuenta se añaden los fondos
        ]);

        $user = $request->user();
        $account = Account::where('id', $request->account_id)
                            ->where('user_id', $user->id)
                            ->first();

        if (!$account) {
            return response()->json(['message' => 'Cuenta no encontrada o no pertenece a este usuario.'], 403);
        }

        DB::beginTransaction();
        try {
            // Restar de la cuenta
            $account->current_balance -= $request->amount;
            if ($account->current_balance < 0 && $account->type !== 'credit_card') { // Cuidado con saldos negativos
                 DB::rollBack();
                 return response()->json(['message' => 'Fondos insuficientes en la cuenta.'], 400);
            }
            $account->save();

            // Añadir a la meta de ahorro
            $savingsGoal->saved_amount += $request->amount;
            if ($savingsGoal->saved_amount >= $savingsGoal->target_amount) {
                $savingsGoal->is_achieved = true;
            }
            $savingsGoal->save();

            // Opcional: Crear una transacción para registrar este movimiento
            $user->transactions()->create([
                'account_id' => $account->id,
                'category_id' => null, // O una categoría específica para "Ahorro"
                'type' => 'expense', // Se considera un gasto para la cuenta
                'amount' => $request->amount,
                'description' => 'Aporte a meta de ahorro: ' . $savingsGoal->name,
                'transaction_date' => now()->toDateString(),
                'payee' => 'Meta de Ahorro',
                'notes' => null,
            ]);

            DB::commit();
            return response()->json(['message' => 'Fondos añadidos a la meta de ahorro exitosamente.', 'savings_goal' => $savingsGoal]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al añadir fondos a meta de ahorro: " . $e->getMessage());
            return response()->json(['message' => 'Error al añadir fondos.', 'error' => $e->getMessage()], 500);
        }
    }
}