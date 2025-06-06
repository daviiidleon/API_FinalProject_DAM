<?php

namespace App\Http\Controllers;

use App\Models\Category; // Importa tu modelo Category
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log; // Para loguear errores

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Esto filtrará las categorías por el usuario autenticado
        $categories = $request->user()->categories()->get();

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            // Validar los datos de la nueva categoría
            $validatedData = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    // Asegura que el nombre sea único para el usuario autenticado
                    // Verifica que el nombre sea único en la tabla 'categories' para el 'user_id' actual.
                    'unique:categories,name,NULL,id,user_id,' . auth()->id()
                ],
                'type' => ['required', 'in:income,expense,transfer'], // Asegúrate de que los tipos sean válidos
                'description' => ['nullable', 'string', 'max:500'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al crear categoría: ", ['errors' => $e->errors(), 'user_id' => auth()->id()]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        // Crear la categoría y asociarla automáticamente al usuario autenticado
        // Asegúrate de que user_id sea fillable en el modelo Category.php
        $category = $request->user()->categories()->create([
            'name' => $validatedData['name'],
            'type' => $validatedData['type'],
            'description' => $validatedData['description'] ?? null, // Usar null si no se proporciona descripción
        ]);

        // ES CRÍTICO: DEVOLVER EL OBJETO DE LA CATEGORÍA CREADA
        // JavaFX necesita este objeto para obtener el ID real.
        return response()->json($category, 201); // 201 Created
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Category $category)
    {
        // Asegúrate de que la categoría pertenezca al usuario autenticado
        if ($category->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Category $category)
    {
        // Asegúrate de que la categoría pertenezca al usuario autenticado
        if ($category->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $validatedData = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:categories,name,'.$category->id.',id,user_id,'.auth()->id(), // Nombre único, excluyendo la categoría actual
                ],
                'type' => ['required', 'in:income,expense,transfer'],
                'description' => ['nullable', 'string', 'max:500'],
            ]);
        } catch (ValidationException $e) {
            Log::error("Error de validación al actualizar categoría: ", ['errors' => $e->errors(), 'category_id' => $category->id, 'user_id' => auth()->id()]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        }

        $category->update([
            'name' => $validatedData['name'],
            'type' => $validatedData['type'],
            'description' => $validatedData['description'] ?? $category->description,
        ]);

        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Category $category)
    {
        // Asegúrate de que la categoría pertenezca al usuario autenticado
        if ($category->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $category->delete();
            return response()->json(['message' => 'Category deleted successfully'], 204); // 204 No Content
        } catch (\Exception $e) {
            Log::error("Error al eliminar categoría: " . $e->getMessage(), ['category_id' => $category->id, 'user_id' => auth()->id()]);
            return response()->json(['message' => 'Error deleting category.'], 500);
        }
    }
}