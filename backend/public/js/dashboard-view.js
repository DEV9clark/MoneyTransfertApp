document.addEventListener('DOMContentLoaded', async () => {
    const api = new DashboardApi('/');
    let transactionsData = [];

    // Currency Logic
    let currentCurrency = localStorage.getItem('selected_currency') || 'XOF';

    // Les montants sont affichés dans LEUR devise : aucune conversion avec des taux fictifs.
    function formatMoney(amount, currency) {
        const cur = currency || currentCurrency || 'XOF';
        try {
            return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: cur }).format(amount);
        } catch (e) {
            return new Intl.NumberFormat('fr-FR').format(amount) + ' ' + cur;
        }
    }

    function receiptLinks(t) {
        if (t.type !== 'send') return '';
        let html = `<a href="/transactions/${t.uuid}/receipt/deposit" target="_blank" rel="noopener" class="text-indigo-600 hover:underline mr-3">Envoi</a>`;
        if (t.status === 'completed') {
            html += `<a href="/transactions/${t.uuid}/receipt/withdrawal" target="_blank" rel="noopener" class="text-green-600 hover:underline">Retrait</a>`;
        }
        return html;
    }

    async function renderDashboard() {
        if (!transactionsData.length) return;

        // Total Volume
        const totalVolume = transactionsData
            .filter(t => t.type === 'send' && t.currency === currentCurrency && ['pending', 'completed'].includes(t.status))
            .reduce((acc, t) => acc + parseFloat(t.amount), 0);
        document.getElementById('stat-volume').innerText = formatMoney(totalVolume);

        // Render Table
        const tableBody = document.getElementById('transactions-table-body');
        tableBody.innerHTML = '';

        // Show ALL transactions
        const transactionsToShow = transactionsData;

        if (transactionsToShow.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Aucune transaction trouvée.</td></tr>`;
            return;
        }

        transactionsToShow.slice(0, 5).forEach(t => { // Show last 5
            const convertedAmount = convertAmount(t.amount, t.currency, currentCurrency);

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
                    <td class="px-6 py-4 text-sm text-gray-600 capitalize">${t.type}</td>
                    <td class="px-6 py-4 text-sm font-bold text-gray-900 text-right">${formatMoney(t.amount, t.currency)}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusColor(t.status)}">
                            ${t.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">${new Date(t.created_at).toLocaleDateString('fr-FR')}</td>
                    <td class="px-6 py-4 text-sm">${receiptLinks(t)}</td>
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
        renderDashboard(); // Re-render table with conversions
    });

    // Helper to fetch stats
    async function fetchStats(filter = 'day') {
        try {
            // Get current currency from state or localstorage
            const currency = localStorage.getItem('selected_currency') || 'XOF';

            const res = await fetch(`/bo-api/transactions/stats?filter=${filter}&currency=${currency}`, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });
            const data = await res.json();

            // Update Volume
            document.getElementById('stat-volume').innerText = formatMoney(data.volume);

            // Profit
            let totalFeesRef = 0;
            const profitEl = document.getElementById('stat-profit');
            if (profitEl) {
                // If the data.fees is in the requested currency, just display it.
                // Note: The API sums the fee_amount for transactions of this currency.
                profitEl.innerText = formatMoney(data.fees);
            }

            // Highlight active button
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

    // Expose changeFilter globally
    window.changeFilter = (filter) => {
        fetchStats(filter);
    };

    try {
        // 1. Fetch Clients Count
        const clients = await api.getItems('clients');
        document.getElementById('stat-clients').innerText = clients ? clients.length : 0;

        // 2. Fetch Agents (Users)
        const users = await api.getItems('users');
        document.getElementById('stat-agents').innerText = users ? users.length : 0;

        // 3. Fetch Transactions (for table)
        const transactions = await api.getItems('transactions');
        if (transactions) {
            transactionsData = transactions; // Store for re-rendering
            renderDashboard(); // Initial render of table
        }

        // 4. Fetch Initial Stats
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
