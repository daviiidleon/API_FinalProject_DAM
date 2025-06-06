<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Account;
use App\Models\Transaction; // Import Transaction model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    /**
     * Helper method to calculate spent_amount for a given budget.
     * This will be called internally before returning budgets.
     */
    private function calculateSpentAmount(Budget $budget): float
    {
        // Only sum 'expense' transactions that fall within the budget's date range
        // and are associated with the budget's category and/or account.
        // Adjust this logic based on how your budgets are defined (per category, per account, or both).

        // For simplicity, let's assume transactions linked to the budget's category AND account within date range.
        // If a budget can be just by category OR just by account, adjust the 'when' clauses.
        $spent = $budget->user->transactions()
            ->whereBetween('transaction_date', [$budget->start_date, $budget->end_date])
            ->where('type', 'expense') // Only consider expenses for spent amount
            ->when($budget->category_id, function ($query) use ($budget) {
                return $query->where('category_id', $budget->category_id);
            })
            ->when($budget->account_id, function ($query) use ($budget) {
                return $query->where('account_id', $budget->account_id);
            })
            ->sum('amount');
        
        return (float) $spent; // Ensure it's a float
    }

    /**
     * Display a listing of the user's budgets.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Eager load category and account relationships for each budget
        $budgets = $request->user()->budgets()->with(['category', 'account'])->orderBy('name')->get();
        
        // Recalculate spent_amount for each budget before returning
        foreach ($budgets as $budget) {
            $budget->spent_amount = $this->calculateSpentAmount($budget);
        }

        // Devolvemos la lista de presupuestos directamente, no envuelta en 'data' por defecto
        return response()->json($budgets);
    }

    /**
     * Store a newly created budget in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'budget_amount' => ['required', 'numeric', 'min:0.01'],
                'category_id' => ['nullable', 'exists:categories,id'],
                // ¡CAMBIO CLAVE AQUÍ! account_id ahora es OBLIGATORIO
                'account_id' => ['required', 'exists:accounts,id'], 
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'is_active' => ['boolean'],
            ]);

            // --- AÑADIR ESTAS LÍNEAS PARA DEPURACIÓN ---
            Log::debug('Datos validados recibidos en BudgetController@store:', $validatedData);
            if (!isset($validatedData['account_id'])) {
                Log::error('account_id NO ESTÁ PRESENTE en validatedData antes de crear el presupuesto!');
            } else {
                Log::debug('account_id SÍ ESTÁ PRESENTE en validatedData: ' . $validatedData['account_id']);
            }
            // --- FIN DE LÍNEAS DE DEPURACIÓN ---

        } catch (ValidationException $e) {
            Log::error("Error de validación al crear presupuesto: ", ['errors' => $e->errors()]);
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        }

        $user = $request->user();

        // Validate category ownership
        if (isset($validatedData['category_id'])) {
            $category = Category::where('id', $validatedData['category_id'])
                                 ->where('user_id', $user->id)
                                 ->first();
            if (!$category) {
                return response()->json(['message' => 'Categoría no encontrada o no pertenece a este usuario.'], 403);
            }
        }

        // Validate account ownership and check for budget overflow (accumulated sum)
        // Ya que account_id es required, sabemos que validatedData['account_id'] existirá
        $account = Account::where('id', $validatedData['account_id'])
                           ->where('user_id', $user->id)
                           ->first();
        if (!$account) {
            // Esto no debería suceder si 'exists:accounts,id' y 'user_id' check funcionan.
            // Pero es un buen fallback si el token de autenticación no es el esperado.
            return response()->json(['message' => 'Cuenta no encontrada o no pertenece a este usuario.'], 403);
        }

        // Calculate the total budgeted amount for this account, INCLUDING the new budget
        $totalBudgetedForAccount = Budget::where('user_id', $user->id)
                                         ->where('account_id', $validatedData['account_id'])
                                         ->sum('budget_amount');
        
        $newTotalBudgeted = $totalBudgetedForAccount + $validatedData['budget_amount'];

        if ($newTotalBudgeted > $account->current_balance) {
            return response()->json([
                'message' => 'El monto total de los presupuestos para la cuenta ' . $account->name . ' excede su balance actual.',
                'current_balance' => $account->current_balance,
                'new_total_budgeted' => $newTotalBudgeted,
                'already_budgeted' => $totalBudgetedForAccount // Añadir para más detalle en el cliente
            ], 422); // Unprocessable Entity
        }
        
        $validatedData['spent_amount'] = 0.00; // Initial spent amount is 0 when creating

        $budget = $user->budgets()->create($validatedData);

        // Recalculate spent_amount for the newly created budget before returning
        $budget->spent_amount = $this->calculateSpentAmount($budget);

        // Load category and account relationships before returning to ensure the client gets full objects
        return response()->json(['message' => 'Presupuesto creado exitosamente.', 'budget' => $budget->load(['category', 'account'])], 201);
    }

    /**
     * Display the specified budget.
     *
     * @param  \App\Models\Budget  $budget
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Budget $budget)
    {
        if ($budget->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado a este presupuesto.'], 403);
        }
        // Recalculate spent_amount for the specific budget before returning
        $budget->spent_amount = $this->calculateSpentAmount($budget);

        return response()->json($budget->load(['category', 'account']));
    }

    /**
     * Update the specified budget in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Budget  $budget
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Budget $budget)
    {
        if ($budget->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado para actualizar este presupuesto.'], 403);
        }

        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'budget_amount' => ['required', 'numeric', 'min:0.01'],
                'category_id' => ['nullable', 'exists:categories,id'],
                // ¡CAMBIO CLAVE AQUÍ! account_id ahora es OBLIGATORIO
                'account_id' => ['required', 'exists:accounts,id'],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'is_active' => ['boolean'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al actualizar presupuesto: ", ['errors' => $e->errors()]);
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        }

        $user = $request->user();

        // Validate category ownership
        if (isset($validatedData['category_id'])) {
            $category = Category::where('id', $validatedData['category_id'])
                                 ->where('user_id', $user->id)
                                 ->first();
            if (!$category) {
                return response()->json(['message' => 'Categoría no encontrada o no pertenece a este usuario.'], 403);
            }
        }
        
        // Validate account ownership and check for budget overflow on update
        // Ya que account_id es required, sabemos que validatedData['account_id'] existirá
        $account = Account::where('id', $validatedData['account_id'])
                           ->where('user_id', $user->id)
                           ->first();
        if (!$account) {
            return response()->json(['message' => 'Cuenta no encontrada o no pertenece a este usuario.'], 403);
        }

        // Calculate the total budgeted amount for this account, EXCLUDING the current budget being updated
        $totalBudgetedForAccount = Budget::where('user_id', $user->id)
                                         ->where('account_id', $validatedData['account_id'])
                                         ->where('id', '!=', $budget->id) // Exclude the current budget being updated
                                         ->sum('budget_amount');
        
        $newTotalBudgeted = $totalBudgetedForAccount + $validatedData['budget_amount'];

        if ($newTotalBudgeted > $account->current_balance) {
            return response()->json([
                'message' => 'El monto total de los presupuestos para la cuenta ' . $account->name . ' excede su balance actual.',
                'current_balance' => $account->current_balance,
                'new_total_budgeted' => $newTotalBudgeted,
                'already_budgeted' => $totalBudgetedForAccount // Añadir para más detalle en el cliente
            ], 422);
        }

        $budget->update($validatedData);
        // Recalculate spent_amount for the updated budget before returning
        $budget->spent_amount = $this->calculateSpentAmount($budget);

        // Load category and account relationships before returning
        return response()->json(['message' => 'Presupuesto actualizado exitosamente.', 'budget' => $budget->load(['category', 'account'])]);
    }

    /**
     * Remove the specified budget from storage.
     *
     * @param  \App\Models\Budget  $budget
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Budget $budget)
    {
        if ($budget->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado para eliminar este presupuesto.'], 403);
        }

        $budget->delete();
        return response()->json(['message' => 'Presupuesto eliminado exitosamente.'], 204);
    }
}