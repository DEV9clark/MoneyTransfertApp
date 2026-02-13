# Inline Edit Package for Laravel

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Package Laravel réutilisable pour l'édition inline de champs avec sauvegarde automatique via API.

## ✨ Fonctionnalités

- ✅ Double-clic pour éditer n'importe quel champ
- ✅ Sauvegarde automatique (Entrée ou clic ailleurs)
- ✅ Annulation facile (touche Échap)
- ✅ Notifications toast de succès/erreur
- ✅ Support CSRF automatique
- ✅ Validation intégrée
- ✅ Champs imbriqués (ex: `client.name`)
- ✅ Hooks avant/après mise à jour
- ✅ Mise à jour en masse
- ✅ 100% personnalisable

## 📦 Installation

### 1. Via Composer (repository local)

Dans le `composer.json` de votre projet :

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../money_transfer_app/backend/packages/inline-edit"
        }
    ],
    "require": {
        "money-transfer/inline-edit": "@dev"
    }
}
```

Puis :

```bash
composer update money-transfer/inline-edit
```

### 2. Publier les assets

```bash
# Publier JS et CSS
php artisan vendor:publish --tag=inline-edit-assets

# Publier la configuration (optionnel)
php artisan vendor:publish --tag=inline-edit-config
```

## 🚀 Utilisation rapide

### 1. Ajouter le trait à votre modèle

```php
use MoneyTransfer\InlineEdit\Traits\HasInlineEdit;

class Transaction extends Model
{
    use HasInlineEdit;
    
    // Définir les champs éditables (optionnel)
    protected $inlineEditableFields = [
        'reference',
        'type',
        'status',
        'notes',
        'client.name', // Champs imbriqués supportés
    ];
    
    // Règles de validation personnalisées (optionnel)
    protected $inlineEditValidationRules = [
        'reference' => ['required', 'string', 'max:50', 'unique:transactions,reference'],
        'amount' => ['required', 'numeric', 'min:0'],
    ];
}
```

### 2. Ajouter les routes

Dans `routes/api.php` :

```php
use MoneyTransfer\InlineEdit\Http\Controllers\InlineEditController;

Route::middleware('auth:sanctum')->group(function () {
    // Route générique pour tous les modèles
    Route::patch('/{model}/{id}', [InlineEditController::class, 'update']);
    
    // Mise à jour en masse (optionnel)
    Route::post('/bulk-update', [InlineEditController::class, 'bulkUpdate']);
});
```

### 3. Inclure les assets dans votre layout

```html
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/inline-edit.css') }}">
</head>
<body>
    <!-- Votre contenu -->
    
    <script src="{{ asset('js/inline-edit.js') }}"></script>
</body>
```

### 4. Marquer les éléments éditables

```html
<table>
    <tr>
        <td>
            <span class="editable-field" 
                  data-id="{{ $transaction->id }}" 
                  data-field="reference"
                  title="Double-cliquez pour éditer">
                {{ $transaction->reference }}
            </span>
        </td>
        <td>
            <span class="editable-field" 
                  data-id="{{ $transaction->id }}" 
                  data-field="client.name">
                {{ $transaction->client->name }}
            </span>
        </td>
    </tr>
</table>
```

### 5. Initialiser JavaScript

```javascript
initInlineEdit({
    selector: '.editable-field',
    apiEndpoint: '/api/transactions',
    method: 'PATCH',
    onSave: (result, id, field, value) => {
        console.log('✓ Sauvegardé:', { id, field, value });
    }
});
```

## ⚙️ Configuration

Le fichier `config/inline-edit.php` permet de configurer :

```php
return [
    'enabled' => true,
    'middleware' => ['auth:sanctum'],
    'default_method' => 'PATCH',
    'show_toast' => true,
    'allowed_models' => [], // Vide = tous autorisés
    'validation_rules' => [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email'],
        // ...
    ],
];
```

## 🎯 Exemples avancés

### Hooks personnalisés

```php
class Transaction extends Model
{
    use HasInlineEdit;
    
    protected function beforeInlineUpdate(string $field, $value): void
    {
        // Logique avant mise à jour
        Log::info("Modification de {$field} vers {$value}");
    }
    
    protected function afterInlineUpdate(string $field, $value): void
    {
        // Logique après mise à jour
        event(new TransactionUpdated($this, $field, $value));
    }
}
```

### Mise à jour en masse

```javascript
fetch('/api/bulk-update', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        updates: [
            { model: 'transactions', id: 1, field: 'status', value: 'completed' },
            { model: 'transactions', id: 2, field: 'status', value: 'completed' }
        ]
    })
});
```

## 🔐 Sécurité

- ✅ Protection CSRF automatique
- ✅ Validation côté serveur
- ✅ Liste blanche de modèles autorisés
- ✅ Vérification des champs éditables
- ✅ Middleware d'authentification

## 📱 Responsive

Le package est entièrement responsive avec :
- Zone de clic agrandie sur mobile
- Taille de police adaptée (évite le zoom iOS)
- Notifications toast responsive

## 🧪 Tests

```bash
composer test
```

## 📄 Licence

MIT

## 👤 Auteur

Money Transfer App

## 🤝 Contribution

Les contributions sont les bienvenues !
