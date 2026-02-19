<?php

namespace MoneyTransfer\InlineEdit\Traits;

use Illuminate\Support\Facades\Validator;

trait HasInlineEdit
{
    /**
     * Vérifier si un champ est éditable inline
     * 
     * @param string $field
     * @return bool
     */
    public function isInlineEditable(string $field): bool
    {
        // Si aucun champ n'est défini, tous sont éditables
        if (empty($this->inlineEditableFields)) {
            return !in_array($field, $this->getHidden());
        }

        return in_array($field, $this->inlineEditableFields);
    }

    /**
     * Obtenir les règles de validation pour un champ
     * 
     * @param string $field
     * @return array
     */
    public function getInlineEditValidationRules(string $field): array
    {
        // Règles personnalisées du modèle
        if (isset($this->inlineEditValidationRules[$field])) {
            return $this->inlineEditValidationRules[$field];
        }

        // Règles par défaut de la config
        $defaultRules = config('inline-edit.validation_rules', []);
        
        return $defaultRules[$field] ?? [];
    }

    /**
     * Mettre à jour un champ via édition inline
     * 
     * @param string $field
     * @param mixed $value
     * @return bool
     * @throws \Exception
     */
    public function updateInlineField(string $field, $value): bool
    {
        // Vérifier si le champ est éditable
        if (!$this->isInlineEditable($field)) {
            throw new \Exception("Le champ '{$field}' n'est pas éditable inline");
        }

        // Gérer les champs imbriqués (ex: client.name)
        if (str_contains($field, '.')) {
            return $this->updateNestedField($field, $value);
        }

        // Valider
        $rules = $this->getInlineEditValidationRules($field);
        
        if (!empty($rules)) {
            $validator = Validator::make(
                [$field => $value],
                [$field => $rules]
            );

            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }
        }

        // Mettre à jour
        $this->$field = $value;
        
        return $this->save();
    }

    /**
     * Mettre à jour un champ imbriqué
     * 
     * @param string $field
     * @param mixed $value
     * @return bool
     */
    protected function updateNestedField(string $field, $value): bool
    {
        $parts = explode('.', $field);
        $relation = $parts[0];
        $relationField = $parts[1];

        // Vérifier que la relation existe
        if (!method_exists($this, $relation)) {
            throw new \Exception("La relation '{$relation}' n'existe pas");
        }

        $relatedModel = $this->$relation;

        if (!$relatedModel) {
            throw new \Exception("Aucun enregistrement trouvé pour la relation '{$relation}'");
        }

        // Valider
        $rules = $relatedModel->getInlineEditValidationRules($relationField);
        
        if (!empty($rules)) {
            $validator = Validator::make(
                [$relationField => $value],
                [$relationField => $rules]
            );

            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }
        }

        // Mettre à jour
        $relatedModel->$relationField = $value;
        
        return $relatedModel->save();
    }

    /**
     * Hook appelé avant la mise à jour inline
     * 
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function beforeInlineUpdate(string $field, $value): void
    {
        // À surcharger dans le modèle si nécessaire
    }

    /**
     * Hook appelé après la mise à jour inline
     * 
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function afterInlineUpdate(string $field, $value): void
    {
        // À surcharger dans le modèle si nécessaire
    }
}
