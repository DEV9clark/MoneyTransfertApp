<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Exemple - Édition Inline</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-800">Liste des Transactions</h1>
            
            <div class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700">
                <p class="font-semibold">💡 Instructions :</p>
                <ul class="list-disc list-inside mt-2 text-sm">
                    <li>Double-cliquez sur un nom de client pour le modifier</li>
                    <li>Appuyez sur <kbd class="px-2 py-1 bg-white rounded border">Entrée</kbd> pour sauvegarder</li>
                    <li>Appuyez sur <kbd class="px-2 py-1 bg-white rounded border">Échap</kbd> pour annuler</li>
                </ul>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Référence</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client (Double-clic pour éditer)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="transactions-tbody">
                    <!-- Les données seront insérées ici -->
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">#TXN001</td>
                        <td class="px-6 py-4">
                            <span class="editable-field text-sm font-medium text-gray-900 cursor-pointer hover:bg-yellow-50 px-2 py-1 rounded transition-colors" 
                                  data-id="1" 
                                  data-field="client_name"
                                  title="Double-cliquez pour éditer">
                                Jean Dupont
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">Envoi</td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900">50 000 CFA</td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                Complété
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">#TXN002</td>
                        <td class="px-6 py-4">
                            <span class="editable-field text-sm font-medium text-gray-900 cursor-pointer hover:bg-yellow-50 px-2 py-1 rounded transition-colors" 
                                  data-id="2" 
                                  data-field="client_name"
                                  title="Double-cliquez pour éditer">
                                Marie Martin
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">Retrait</td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900">75 000 CFA</td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                En attente
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">#TXN003</td>
                        <td class="px-6 py-4">
                            <span class="editable-field text-sm font-medium text-gray-900 cursor-pointer hover:bg-yellow-50 px-2 py-1 rounded transition-colors" 
                                  data-id="3" 
                                  data-field="client_name"
                                  title="Double-cliquez pour éditer">
                                Pierre Dubois
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">Envoi</td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900">100 000 CFA</td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                Complété
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Section d'exemples de code -->
        <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold mb-4 text-gray-800">📝 Exemples d'utilisation</h2>
            
            <div class="space-y-4">
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">1. Utilisation simple :</h3>
                    <pre class="bg-gray-800 text-green-400 p-4 rounded overflow-x-auto"><code>// Initialiser l'édition inline
initInlineEdit({
    selector: '.editable-field',
    apiEndpoint: '/api/transactions'
});</code></pre>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">2. Avec callbacks personnalisés :</h3>
                    <pre class="bg-gray-800 text-green-400 p-4 rounded overflow-x-auto"><code>initInlineEdit({
    selector: '.editable-field',
    apiEndpoint: '/api/transactions',
    method: 'PATCH', // ou 'PUT'
    onSave: (result, id, field, value) => {
        console.log('✓ Sauvegardé:', { id, field, value });
        // Rafraîchir d'autres parties de l'UI
    },
    onError: (error, id, field) => {
        console.error('✗ Erreur:', error);
        alert('Impossible de sauvegarder');
    }
});</code></pre>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">3. HTML requis :</h3>
                    <pre class="bg-gray-800 text-green-400 p-4 rounded overflow-x-auto"><code>&lt;span class="editable-field" 
      data-id="123"           &lt;!-- ID de l'enregistrement --&gt;
      data-field="client_name"&lt;!-- Nom du champ à modifier --&gt;
&gt;
    Nom du client
&lt;/span&gt;</code></pre>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">4. Exemple avec GraphQL :</h3>
                    <pre class="bg-gray-800 text-green-400 p-4 rounded overflow-x-auto"><code>// Créer une fonction wrapper pour GraphQL
function initInlineEditGraphQL() {
    return new InlineEdit({
        selector: '.editable-field',
        apiEndpoint: '/graphql', // Sera ignoré
        onSave: async (result, id, field, value) => {
            // Faire la mutation GraphQL
            const mutation = `
                mutation UpdateTransaction($id: ID!, $data: TransactionInput!) {
                    updateTransaction(id: $id, data: $data) {
                        id
                        ${field}
                    }
                }
            `;
            
            const response = await fetch('/graphql', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    query: mutation,
                    variables: { 
                        id: id, 
                        data: { [field]: value } 
                    }
                })
            });
            
            return response.json();
        }
    });
}</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Inclure le script inline-edit -->
    <script src="/js/inline-edit.js"></script>
    
    <!-- Initialiser l'édition inline -->
    <script>
        // Initialisation simple
        initInlineEdit({
            selector: '.editable-field',
            apiEndpoint: '/api/transactions',
            method: 'PATCH',
            onSave: (result, id, field, value) => {
                console.log('✓ Modification sauvegardée:', { id, field, value, result });
            },
            onError: (error, id, field) => {
                console.error('✗ Erreur de sauvegarde:', { id, field, error });
            }
        });
    </script>
</body>
</html>
