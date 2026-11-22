<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionInlineEditController extends Controller
{
    /**
     * Mise à jour inline d'une transaction
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        dd($request->all());
        try {
            // Trouver la transaction
            $transaction = Transaction::findOrFail($id);
            
            // Récupérer le champ à modifier
            $field = array_keys($request->except('_token', '_method'))[0] ?? null;
            $value = $request->input($field);
            
            if (!$field) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun champ à modifier'
                ], 400);
            }
            
            // Gérer les champs imbriqués (ex: client.name)
            if (str_contains($field, '.')) {
                return $this->updateNestedField($transaction, $field, $value);
            }
            
            // Validation selon le champ
            $rules = $this->getValidationRules($field);
            
            if (!empty($rules)) {
                $validator = Validator::make([$field => $value], $rules);
                
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => $validator->errors()->first(),
                        'errors' => $validator->errors()
                    ], 422);
                }
            }
            
            // Mettre à jour le champ
            $transaction->$field = $value;
            $transaction->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Transaction mise à jour avec succès',
                'data' => $transaction->fresh()
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
            
        } catch (\Exception $e) {
            \Log::error('Erreur mise à jour inline transaction: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }
    
    /**
     * Mise à jour d'un champ imbriqué (ex: client.name)
     * 
     * @param Transaction $transaction
     * @param string $field
     * @param mixed $value
     * @return \Illuminate\Http\JsonResponse
     */
    private function updateNestedField($transaction, $field, $value)
    {
        // Exemple: "client.name" -> mettre à jour le nom du client
        $parts = explode('.', $field);
        
        if ($parts[0] === 'client' && $transaction->client) {
            $clientField = $parts[1];
            
            // Validation
            $rules = $this->getValidationRules("client.$clientField");
            
            if (!empty($rules)) {
                $validator = Validator::make([$clientField => $value], $rules);
                
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => $validator->errors()->first()
                    ], 422);
                }
            }
            
            // Mettre à jour le client
            $transaction->client->$clientField = $value;
            $transaction->client->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Client mis à jour avec succès',
                'data' => $transaction->fresh()
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Champ imbriqué non supporté'
        ], 400);
    }
    
    /**
     * Règles de validation selon le champ
     * 
     * @param string $field
     * @return array
     */
    private function getValidationRules($field)
    {
        $rules = [
            'reference' => ['required', 'string', 'max:50'],
            'type' => ['required', 'string', 'in:envoi,retrait,depot'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'in:USD,EUR,XOF'],
            'status' => ['required', 'string', 'in:pending,completed,failed,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
            
            // Champs client
            'client.name' => ['required', 'string', 'max:255'],
            'client.phone' => ['required', 'string', 'max:20'],
            'client.email' => ['nullable', 'email', 'max:255'],
        ];
        
        return $rules[$field] ?? [];
    }
    
    /**
     * Mise à jour en masse (optionnel)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'updates' => 'required|array',
            'updates.*.id' => 'required|exists:transactions,id',
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
            $transaction = Transaction::find($update['id']);
            $field = $update['field'];
            $value = $update['value'];
            
            // Validation
            $rules = $this->getValidationRules($field);
            if (!empty($rules)) {
                $validator = Validator::make([$field => $value], $rules);
                if ($validator->fails()) {
                    $results[] = [
                        'id' => $update['id'],
                        'success' => false,
                        'message' => $validator->errors()->first()
                    ];
                    continue;
                }
            }
            
            $transaction->$field = $value;
            $transaction->save();
            
            $results[] = [
                'id' => $update['id'],
                'success' => true,
                'data' => $transaction
            ];
        }
        
        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }
}
