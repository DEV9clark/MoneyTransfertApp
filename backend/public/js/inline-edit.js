/**
 * Inline Edit - Fonction générique pour l'édition inline de champs dans les tableaux
 * 
 * @author Money Transfer App
 * @version 1.0.0
 */

class InlineEdit {
    /**
     * Initialise l'édition inline sur un élément
     * 
     * @param {Object} options - Configuration
     * @param {string} options.selector - Sélecteur CSS des éléments éditables (ex: '.editable-field')
     * @param {string} options.apiEndpoint - URL de l'API pour sauvegarder (ex: '/api/transactions')
     * @param {Function} options.onSave - Callback appelé après sauvegarde réussie (optionnel)
     * @param {Function} options.onError - Callback appelé en cas d'erreur (optionnel)
     * @param {string} options.method - Méthode HTTP (défaut: 'PATCH')
     * @param {boolean} options.showToast - Afficher les notifications (défaut: true)
     */
    constructor(options = {}) {
        this.selector = options.selector || '.editable-field';
        this.apiEndpoint = options.apiEndpoint;
        this.onSave = options.onSave || null;
        this.onError = options.onError || null;
        this.method = options.method || 'PATCH';
        this.showToast = options.showToast !== false;
        this.currentEditingElement = null;

        this.init();
    }

    /**
     * Initialise les événements sur tous les éléments éditables
     */
    init() {
        document.addEventListener('dblclick', (e) => {
            const target = e.target.closest(this.selector);
            if (target && !target.classList.contains('editing')) {
                this.enableEdit(target);
            }
        });

        // Fermer l'édition si on clique ailleurs
        document.addEventListener('click', (e) => {
            if (this.currentEditingElement &&
                !this.currentEditingElement.contains(e.target) &&
                !e.target.closest(this.selector)) {
                this.cancelEdit();
            }
        });
    }

    /**
     * Active le mode édition sur un élément
     * @param {HTMLElement} element - L'élément à éditer
     */
    enableEdit(element) {
        // Annuler toute édition en cours
        if (this.currentEditingElement) {
            this.cancelEdit();
        }

        this.currentEditingElement = element;

        // Récupérer les données de l'élément
        const recordId = element.dataset.id;
        const fieldName = element.dataset.field;
        const currentValue = element.textContent.trim();

        // Sauvegarder la valeur originale
        element.dataset.originalValue = currentValue;

        // Créer l'input
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentValue;
        input.className = 'inline-edit-input px-2 py-1 border border-blue-500 rounded focus:outline-none focus:ring-2 focus:ring-blue-400 w-full';

        // Vider l'élément et ajouter l'input
        element.innerHTML = '';
        element.appendChild(input);
        element.classList.add('editing');

        // Focus sur l'input
        input.focus();
        input.select();

        // Événements sur l'input
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.saveEdit(element, recordId, fieldName, input.value);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.cancelEdit();
            }
        });

        input.addEventListener('blur', () => {
            // Petit délai pour permettre le clic sur d'autres éléments
            setTimeout(() => {
                if (this.currentEditingElement === element) {
                    this.saveEdit(element, recordId, fieldName, input.value);
                }
            }, 200);
        });
    }

    /**
     * Sauvegarde la modification
     * @param {HTMLElement} element - L'élément édité
     * @param {string} recordId - ID de l'enregistrement
     * @param {string} fieldName - Nom du champ
     * @param {string} newValue - Nouvelle valeur
     */
    async saveEdit(element, recordId, fieldName, newValue) {
        const originalValue = element.dataset.originalValue;

        // Si la valeur n'a pas changé, annuler
        if (newValue.trim() === originalValue) {
            this.cancelEdit();
            return;
        }

        // Afficher un loader
        element.innerHTML = '<span class="inline-block animate-spin">⏳</span>';

        try {
            // Construire l'URL de l'API
            const url = `${this.apiEndpoint}/${recordId}`;

            // Préparer les données
            const data = {
                [fieldName]: newValue.trim()
            };

            // Envoyer la requête
            const response = await fetch(url, {
                method: this.method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    // Ajouter le token CSRF si disponible
                    ...(this.getCsrfToken() && { 'X-CSRF-TOKEN': this.getCsrfToken() })
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }

            const result = await response.json();

            // Restaurer l'élément avec la nouvelle valeur
            element.textContent = newValue.trim();
            element.classList.remove('editing');
            this.currentEditingElement = null;

            // Animation de succès
            element.classList.add('bg-green-100');
            setTimeout(() => {
                element.classList.remove('bg-green-100');
            }, 1000);

            // Afficher un toast de succès
            if (this.showToast) {
                this.showNotification('✓ Modification enregistrée', 'success');
            }

            // Callback de succès
            if (this.onSave) {
                this.onSave(result, recordId, fieldName, newValue);
            }

        } catch (error) {
            console.error('Erreur lors de la sauvegarde:', error);

            // Restaurer la valeur originale
            element.textContent = originalValue;
            element.classList.remove('editing');
            this.currentEditingElement = null;

            // Afficher un toast d'erreur
            if (this.showToast) {
                this.showNotification('✗ Erreur lors de la sauvegarde', 'error');
            }

            // Callback d'erreur
            if (this.onError) {
                this.onError(error, recordId, fieldName);
            }
        }
    }

    /**
     * Annule l'édition en cours
     */
    cancelEdit() {
        if (!this.currentEditingElement) return;

        const originalValue = this.currentEditingElement.dataset.originalValue;
        this.currentEditingElement.textContent = originalValue;
        this.currentEditingElement.classList.remove('editing');
        this.currentEditingElement = null;
    }

    /**
     * Récupère le token CSRF
     * @returns {string|null}
     */
    getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : null;
    }

    /**
     * Affiche une notification toast
     * @param {string} message - Message à afficher
     * @param {string} type - Type de notification ('success' ou 'error')
     */
    showNotification(message, type = 'success') {
        // Créer le toast
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white font-medium z-50 transition-all transform translate-x-0 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
        toast.textContent = message;

        document.body.appendChild(toast);

        // Animation d'entrée
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 10);

        // Supprimer après 3 secondes
        setTimeout(() => {
            toast.style.transform = 'translateX(400px)';
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }

    /**
     * Détruit l'instance et supprime les événements
     */
    destroy() {
        this.cancelEdit();
        // Note: Pour une vraie destruction, il faudrait stocker les listeners
    }
}

/**
 * Fonction helper pour initialiser rapidement l'édition inline
 * 
 * @param {Object} options - Configuration (voir InlineEdit constructor)
 * @returns {InlineEdit} Instance de InlineEdit
 * 
 * @example
 * // Utilisation simple
 * initInlineEdit({
 *     selector: '.editable-name',
 *     apiEndpoint: '/api/transactions'
 * });
 * 
 * @example
 * // Utilisation avancée avec callbacks
 * initInlineEdit({
 *     selector: '.editable-field',
 *     apiEndpoint: '/api/transactions',
 *     method: 'PUT',
 *     onSave: (result, id, field, value) => {
 *         console.log('Sauvegardé:', result);
 *         // Rafraîchir d'autres parties de l'UI si nécessaire
 *     },
 *     onError: (error, id, field) => {
 *         console.error('Erreur:', error);
 *     }
 * });
 */
function initInlineEdit(options) {
    return new InlineEdit(options);
}

// Export pour utilisation en module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { InlineEdit, initInlineEdit };
}
