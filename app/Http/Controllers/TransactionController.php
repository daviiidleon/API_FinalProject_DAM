<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account; // Para verificar la pertenencia de la cuenta
use App\Models\Category; // Para verificar la pertenencia de la categoría
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB; // Para transacciones de DB
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = $request->user()->transactions()
                            ->with('account', 'category') // Cargar relaciones
                            ->orderBy('transaction_date', 'desc')
                            ->get();
        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'account_id' => ['required', 'exists:accounts,id'], // Asegúrate que sea 'id' o 'account_id'
                'category_id' => ['nullable', 'exists:categories,id'],
                'type' => ['required', 'string', 'in:income,expense,transfer'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'description' => ['nullable', 'string', 'max:255'],
                'transaction_date' => ['required', 'date'],
                'payee' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al crear transacción: ", ['errors' => $e->errors()]);
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        }

        $user = $request->user();

        // Validar que la cuenta y categoría pertenezcan al usuario
        $account = Account::where('id', $validatedData['account_id']) // o 'account_id'
                            ->where('user_id', $user->id)
                            ->first();
        if (!$account) {
            return response()->json(['message' => 'Cuenta no encontrada o no pertenece a este usuario.'], 403);
        }

        if ($validatedData['category_id']) {
            $category = Category::where('id', $validatedData['category_id'])
                                ->where('user_id', $user->id)
                                ->first();
            if (!$category) {
                return response()->json(['message' => 'Categoría no encontrada o no pertenece a este usuario.'], 403);
            }
        }

        DB::beginTransaction();
        try {
            $transaction = $user->transactions()->create($validatedData);

            // Actualizar el saldo de la cuenta
            if ($transaction->type === 'income') {
                $account->current_balance += $transaction->amount;
            } elseif ($transaction->type === 'expense') {
                $account->current_balance -= $transaction->amount;
                // Opcional: Actualizar 'spent_amount' en los presupuestos
                // if ($transaction->category_id) {
                //     Budget::where('user_id', $user->id)
                //         ->where('category_id', $transaction->category_id)
                //         ->where('start_date', '<=', $transaction->transaction_date)
                //         ->where('end_date', '>=', $transaction->transaction_date)
                //         ->increment('spent_amount', $transaction->amount);
                // }
            }
            // Implementar lógica para 'transfer' si es necesario (mover entre dos cuentas)

            $account->save();
            DB::commit();

            return response()->json(['message' => 'Transacción creada exitosamente.', 'transaction' => $transaction->load('account', 'category')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al crear transacción o actualizar saldo: " . $e->getMessage());
            return response()->json(['message' => 'Error al procesar la transacción.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Transaction $transaction)
    {
        if ($transaction->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado a esta transacción.'], 403);
        }
        return response()->json($transaction->load('account', 'category'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado para actualizar esta transacción.'], 403);
        }

        try {
            $validatedData = $request->validate([
                'account_id' => ['required', 'exists:accounts,id'],
                'category_id' => ['nullable', 'exists:categories,id'],
                'type' => ['required', 'string', 'in:income,expense,transfer'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'description' => ['nullable', 'string', 'max:255'],
                'transaction_date' => ['required', 'date'],
                'payee' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al actualizar transacción: ", ['errors' => $e->errors()]);
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        }

        $user = $request->user();

        $oldTransactionAmount = $transaction->amount;
        $oldTransactionType = $transaction->type;
        $oldAccountId = $transaction->account_id;
        $oldCategoryId = $transaction->category_id;

        DB::beginTransaction();
        try {
            // Revertir el saldo de la cuenta antigua
            $oldAccount = Account::where('id', $oldAccountId)->where('user_id', $user->id)->first();
            if ($oldAccount) {
                if ($oldTransactionType === 'income') {
                    $oldAccount->current_balance -= $oldTransactionAmount;
                } elseif ($oldTransactionType === 'expense') {
                    $oldAccount->current_balance += $oldTransactionAmount;
                }
                $oldAccount->save();
            }

            // Actualizar la transacción
            $transaction->update($validatedData);

            // Aplicar el nuevo saldo a la cuenta (podría ser la misma o una nueva)
            $newAccount = Account::where('id', $transaction->account_id)
                                ->where('user_id', $user->id)
                                ->first();
            if (!$newAccount) {
                DB::rollBack();
                return response()->json(['message' => 'Nueva cuenta no encontrada o no pertenece a este usuario.'], 403);
            }

            if ($transaction->type === 'income') {
                $newAccount->current_balance += $transaction->amount;
            } elseif ($transaction->type === 'expense') {
                $newAccount->current_balance -= $transaction->amount;
                // Opcional: Actualizar spent_amount en presupuestos
                // if ($transaction->category_id) {
                //     Budget::where('user_id', $user->id)
                //         ->where('category_id', $transaction->category_id)
                //         ->where('start_date', '<=', $transaction->transaction_date)
                //         ->where('end_date', '>=', $transaction->transaction_date)
                //         ->increment('spent_amount', $transaction->amount);
                // }
            }
            $newAccount->save();

            DB::commit();
            return response()->json(['message' => 'Transacción actualizada exitosamente.', 'transaction' => $transaction->load('account', 'category')]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar transacción o saldo: " . $e->getMessage());
            return response()->json(['message' => 'Error al procesar la actualización de la transacción.', 'error' => $e->getMessage()], 500);
        }
    }


    public function destroy(Transaction $transaction)
    {
        if ($transaction->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado para eliminar esta transacción.'], 403);
        }

        DB::beginTransaction();
        try {
            // Revertir el saldo de la cuenta al eliminar la transacción
            $account = Account::where('id', $transaction->account_id)
                            ->where('user_id', Auth::id())
                            ->first();

            if ($account) {
                if ($transaction->type === 'income') {
                    $account->current_balance -= $transaction->amount;
                } elseif ($transaction->type === 'expense') {
                    $account->current_balance += $transaction->amount;
                    // Opcional: Revertir spent_amount en los presupuestos
                    // if ($transaction->category_id) {
                    //     Budget::where('user_id', Auth::id())
                    //         ->where('category_id', $transaction->category_id)
                    //         ->where('start_date', '<=', $transaction->transaction_date)
                    //         ->where('end_date', '>=', $transaction->transaction_date)
                    //         ->decrement('spent_amount', $transaction->amount);
                    // }
                }
                $account->save();
            } else {
                Log::warning("Cuenta asociada a la transacción no encontrada al intentar eliminar o no pertenece al usuario: " . $transaction->account_id);
                // Podrías decidir si esto es un error crítico o simplemente loggearlo.
            }

            $transaction->delete();
            DB::commit();
            return response()->json(['message' => 'Transacción eliminada exitosamente.'], 204);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar transacción o revertir saldo: " . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar la transacción.', 'error' => $e->getMessage()], 500);
        }
    }
}