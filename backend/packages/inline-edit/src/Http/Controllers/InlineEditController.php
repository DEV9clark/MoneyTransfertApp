<?php

namespace MoneyTransfer\InlineEdit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class InlineEditController extends Controller
{
    /**
     * Mise à jour inline générique
     * 
     * @param Request $request
     * @param string $model - Nom du modèle (ex: "transactions", "clients")
     * @param int $id - ID de l'enregistrement
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $model, int $id)
    {
        try {
            // Résoudre le modèle
            $modelClass = $this->resolveModel($model);
            
            if (!$modelClass) {
                return response()->json([
                    'success' => false,
                    'message' => "Modèle '{$model}' non trouvé"
                ], 404);
            }

            // Trouver l'enregistrement
            $record = $modelClass::findOrFail($id);

            // Vérifier si le modèle utilise le trait HasInlineEdit
            if (!method_exists($record, 'updateInlineField')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce modèle ne supporte pas l\'édition inline'
                ], 400);
            }

            // Récupérer le champ et la valeur
            $field = array_keys($request->except('_token', '_method'))[0] ?? null;
            $value = $request->input($field);

            if (!$field) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun champ à modifier'
                ], 400);
            }

            // Appeler le hook avant mise à jour
            if (method_exists($record, 'beforeInlineUpdate')) {
                $record->beforeInlineUpdate($field, $value);
            }

            // Mettre à jour via le trait
            $record->updateInlineField($field, $value);

            // Appeler le hook après mise à jour
            if (method_exists($record, 'afterInlineUpdate')) {
                $record->afterInlineUpdate($field, $value);
            }

            return response()->json([
                'success' => true,
                'message' => 'Mise à jour réussie',
                'data' => $record->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enregistrement non trouvé'
            ], 404);

        } catch (\Exception $e) {
            \Log::error('Erreur édition inline: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Résoudre le nom du modèle en classe
     * 
     * @param string $model
     * @return string|null
     */
    protected function resolveModel(string $model): ?string
    {
        // Convertir "transactions" en "Transaction"
        $modelName = ucfirst(str_singular($model));

        // Essayer différents namespaces
        $namespaces = [
            "App\\Models\\{$modelName}",
            "App\\{$modelName}",
        ];

        foreach ($namespaces as $namespace) {
            if (class_exists($namespace)) {
                // Vérifier si le modèle est autorisé
                $allowedModels = config('inline-edit.allowed_models', []);
                
                if (!empty($allowedModels) && !in_array($namespace, $allowedModels)) {
                    return null;
                }

                return $namespace;
            }
        }

        return null;
    }

    /**
     * Mise à jour en masse
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'updates' => 'required|array',
            'updates.*.model' => 'required|string',
            'updates.*.id' => 'required|integer',
            'updates.*.field' => 'required|string',
            'updates.*.value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $results = [];

        foreach ($request->updates as $update) {
            try {
                $modelClass = $this->resolveModel($update['model']);
                $record = $modelClass::findOrFail($update['id']);
                
                $record->updateInlineField($update['field'], $update['value']);

                $results[] = [
                    'id' => $update['id'],
                    'success' => true,
                    'data' => $record->fresh()
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'id' => $update['id'] ?? null,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }
}
