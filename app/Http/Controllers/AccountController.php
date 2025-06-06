<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    /**
     * Display a listing of the user's accounts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $accounts = $request->user()->accounts()->orderBy('name')->get();
        return response()->json($accounts);
    }

    /**
     * Store a newly created account in storage.
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
                'current_balance' => ['required', 'numeric'], // <--- Asegúrate de que es 'current_balance'
                'currency' => ['required', 'string', 'size:3'],
                'type' => ['required', 'string', 'in:checking,savings,credit_card,investment,cash,other'], // <--- Asegúrate de que estos son tus ENUMs
                'is_active' => ['boolean'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al crear cuenta: ", ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        $account = $request->user()->accounts()->create([
            'name' => $validatedData['name'],
            'current_balance' => $validatedData['current_balance'], // <--- Asegúrate de que es 'current_balance'
            'currency' => $validatedData['currency'],
            'type' => $validatedData['type'],
            'is_active' => $validatedData['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Cuenta creada exitosamente.',
            'account' => $account
        ], 201);
    }

    /**
     * Display the specified account.
     *
     * @param  \App\Models\Account  $account
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado a esta cuenta.'], 403);
        }
        return response()->json($account);
    }

    /**
     * Update the specified account in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Account  $account
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado para actualizar esta cuenta.'], 403);
        }

        try {
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'current_balance' => ['required', 'numeric'], // <--- Asegúrate de que es 'current_balance'
                'currency' => ['required', 'string', 'size:3'],
                'type' => ['required', 'string', 'in:checking,savings,credit_card,investment,cash,other'], // <--- Asegúrate de que estos son tus ENUMs
                'is_active' => ['boolean'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al actualizar cuenta: ", ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        $account->update($validatedData);
        return response()->json([
            'message' => 'Cuenta actualizada exitosamente.',
            'account' => $account
        ]);
    }

    /**
     * Remove the specified account from storage.
     *
     * @param  \App\Models\Account  $account
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acceso no autorizado para eliminar esta cuenta.'], 403);
        }

        $account->delete();

        return response()->json(['message' => 'Cuenta eliminada exitosamente.'], 204);
    }
}