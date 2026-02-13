/**
 * Exemple d'intégration de l'édition inline dans dashboard-view.js
 * 
 * Ce fichier montre comment intégrer la fonction d'édition inline
 * dans votre tableau de transactions existant
 */

document.addEventListener('DOMContentLoaded', async () => {
    const api = new DashboardApi();
    let transactionsData = [];

    // Currency Logic
    let currentCurrency = localStorage.getItem('selected_currency') || 'USD';
    const rateMap = typeof RATES !== 'undefined' ? RATES : { 'USD': 1, 'EUR': 0.92, 'XOF': 600 };
    const symbolMap = typeof SYMBOLS !== 'undefined' ? SYMBOLS : { 'USD': '$', 'EUR': '€', 'XOF': 'CFA' };

    // ============================================
    // NOUVEAU: Initialiser l'édition inline
    // ============================================
    initInlineEdit({
        selector: '.editable-field',
        apiEndpoint: '/api/transactions',
        method: 'PATCH',
        onSave: (result, id, field, value) => {
            console.log('✓ Transaction mise à jour:', { id, field, value });

            // Mettre à jour les données locales
            const transaction = transactionsData.find(t => t.id == id);
            if (transaction) {
                // Mettre à jour le champ modifié
                if (field.includes('.')) {
                    // Champ imbriqué (ex: client.name)
                    const parts = field.split('.');
                    let obj = transaction;
                    for (let i = 0; i < parts.length - 1; i++) {
                        obj = obj[parts[i]];
                    }
                    obj[parts[parts.length - 1]] = value;
                } else {
                    transaction[field] = value;
                }
            }
        },
        onError: (error, id, field) => {
            console.error('✗ Erreur de mise à jour:', { id, field, error });
        }
    });

    function convertAmount(amount, fromCurrency, toCurrency) {
        if (fromCurrency === toCurrency) return parseFloat(amount);
        const base = parseFloat(amount) / (rateMap[fromCurrency] || 1);
        const target = base * (rateMap[toCurrency] || 1);
        return target;
    }

    function formatMoney(amount, currency = currentCurrency, compact = false) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: currency,
            notation: compact ? 'compact' : 'standard',
            maximumFractionDigits: compact ? 1 : 2
        }).format(amount);
    }

    async function renderDashboard() {
        if (!transactionsData.length) return;

        const tableBody = document.getElementById('transactions-table-body');
        tableBody.innerHTML = '';

        const transactionsToShow = transactionsData;

        if (transactionsToShow.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Aucune transaction trouvée.</td></tr>`;
            return;
        }

        transactionsToShow.slice(0, 5).forEach(t => {
            const convertedAmount = convertAmount(t.amount, t.currency, currentCurrency);

            // ============================================
            // MODIFIÉ: Ajouter la classe editable-field et les data attributes
            // ============================================
            const row = `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">#${t.reference || 'N/A'}</td>
                    <td class="px-6 py-4">
                        <span class="editable-field text-sm font-medium text-gray-900 cursor-pointer hover:bg-yellow-50 px-2 py-1 rounded transition-colors" 
                              data-id="${t.id}" 
                              data-field="client.name"
                              title="Double-cliquez pour éditer">
                            ${t.client ? t.client.name : 'Inconnu'}
                        </span>
                        <span class="block text-xs text-gray-400">${t.client ? t.client.phone : ''}</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="editable-field text-sm text-gray-600 capitalize cursor-pointer hover:bg-yellow-50 px-2 py-1 rounded transition-colors"
                              data-id="${t.id}"
                              data-field="type"
                              title="Double-cliquez pour éditer">
                            ${t.type}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-gray-900 text-right">
                        ${formatMoney(convertedAmount, currentCurrency)}
                        <span class="block text-xs text-gray-400 font-normal">(${formatMoney(t.amount, t.currency)})</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusColor(t.status)}">
                            ${t.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">${new Date(t.created_at).toLocaleDateString()}</td>
                </tr>
            `;
            tableBody.innerHTML += row;
        });
    }

    // Listen for global currency change
    window.addEventListener('currencyChanged', (e) => {
        currentCurrency = e.detail.currency;
        const activeFilter = document.querySelector('button[id^="filter-"].bg-white')?.id.replace('filter-', '') || 'day';
        fetchStats(activeFilter);
        renderDashboard();
    });

    async function fetchStats(filter = 'day') {
        try {
            const currency = localStorage.getItem('selected_currency') || 'USD';
            const res = await fetch(`/api/transactions/stats?filter=${filter}`);
            const data = await res.json();

            let totalVolumeRef = 0;
            if (data.volumes) {
                data.volumes.forEach(item => {
                    totalVolumeRef += convertAmount(item.volume, item.currency, currency);
                });
            }
            document.getElementById('stat-volume').innerText = formatMoney(totalVolumeRef, currency, true);

            let totalFeesRef = 0;
            const profitEl = document.getElementById('stat-profit');
            if (profitEl && data.fees) {
                data.fees.forEach(item => {
                    totalFeesRef += convertAmount(item.fees, item.currency, currency);
                });
                profitEl.innerText = formatMoney(totalFeesRef, currency, true);
            }

            ['day', 'week', 'month', 'year'].forEach(f => {
                const btn = document.getElementById(`filter-${f}`);
                if (f === filter) {
                    btn.classList.add('bg-white', 'text-gray-800', 'shadow-sm');
                    btn.classList.remove('text-gray-500');
                } else {
                    btn.classList.remove('bg-white', 'text-gray-800', 'shadow-sm');
                    btn.classList.add('text-gray-500');
                }
            });

        } catch (e) {
            console.error("Stats Fetch Error", e);
        }
    }

    window.changeFilter = (filter) => {
        fetchStats(filter);
    };

    try {
        const clients = await api.getItems('clients');
        document.getElementById('stat-clients').innerText = clients ? clients.length : 0;

        const users = await api.getItems('users');
        document.getElementById('stat-agents').innerText = users ? users.length : 0;

        const transactions = await api.getItems('transactions');
        if (transactions) {
            transactionsData = transactions;
            renderDashboard();
        }

        fetchStats('day');

    } catch (error) {
        console.error("Dashboard Init Error", error);
        document.getElementById('transactions-table-body').innerHTML = `
            <tr><td colspan="6" class="px-6 py-4 text-center text-red-500">Error loading data. Is GraphQL installed?</td></tr>
        `;
    }
});

function getStatusColor(status) {
    switch (status) {
        case 'completed': return 'bg-green-100 text-green-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'failed': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
