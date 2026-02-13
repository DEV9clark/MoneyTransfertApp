# Installation du package dans un nouveau projet

## 🎯 Méthode recommandée : Repository local

### Étape 1 : Configurer le repository

Dans le `composer.json` de votre **nouveau projet** :

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

### Étape 2 : Installer le package

```bash
composer update money-transfer/inline-edit
```

### Étape 3 : Publier les assets

```bash
# Publier JS et CSS dans public/
php artisan vendor:publish --tag=inline-edit-assets

# Publier la configuration (optionnel)
php artisan vendor:publish --tag=inline-edit-config
```

### Étape 4 : Configurer votre modèle

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MoneyTransfer\InlineEdit\Traits\HasInlineEdit;

class YourModel extends Model
{
    use HasInlineEdit;
    
    protected $inlineEditableFields = [
        'name',
        'email',
        'status',
    ];
}
```

### Étape 5 : Ajouter les routes

Dans `routes/api.php` :

```php
use MoneyTransfer\InlineEdit\Http\Controllers\InlineEditController;

Route::middleware('auth:sanctum')->group(function () {
    Route::patch('/{model}/{id}', [InlineEditController::class, 'update']);
});
```

### Étape 6 : Utiliser dans vos vues

```html
<!DOCTYPE html>
<html>
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/inline-edit.css') }}">
</head>
<body>
    <table>
        <tr>
            <td>
                <span class="editable-field" 
                      data-id="{{ $item->id }}" 
                      data-field="name">
                    {{ $item->name }}
                </span>
            </td>
        </tr>
    </table>

    <script src="{{ asset('js/inline-edit.js') }}"></script>
    <script>
        initInlineEdit({
            selector: '.editable-field',
            apiEndpoint: '/api/your-model'
        });
    </script>
</body>
</html>
```

## ✅ C'est tout !

Votre nouveau projet peut maintenant utiliser l'édition inline. Toutes les mises à jour du package source seront automatiquement disponibles grâce au lien symbolique créé par Composer.
