document.addEventListener('DOMContentLoaded', async () => {
    const api = new DashboardApi();
    let transactionsData = [];

    // Currency Logic
    let currentCurrency = localStorage.getItem('selected_currency') || 'USD';
    const rateMap = typeof RATES !== 'undefined' ? RATES : { 'USD': 1, 'EUR': 0.92, 'XOF': 600 };
    const symbolMap = typeof SYMBOLS !== 'undefined' ? SYMBOLS : { 'USD': '$', 'EUR': '€', 'XOF': 'CFA' };

    function convertAmount(amount, fromCurrency, toCurrency) {
        if (fromCurrency === toCurrency) return parseFloat(amount);

        // Base is XOF (from rateMap logic: XOF: 600, USD: 1. So Base is USD actually?)
        // Let's check rateMap: { 'USD': 1, 'EUR': 0.92, 'XOF': 600 }
        // This implies USD is base 1.
        // Amount / Rate_From = BaseUSD
        // BaseUSD * Rate_To = Target
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
        renderDashboard(); // Re-render table with conversions
    });

    // Helper to fetch stats
    async function fetchStats(filter = 'day') {
        try {
            // Get current currency from state or localstorage
            const currency = localStorage.getItem('selected_currency') || 'USD';
            // Note: currency param is no longer used by backend for filtering, but we keep it or ignore it.
            const res = await fetch(`/api/transactions/stats?filter=${filter}`);
            const data = await res.json();

            // Aggregation Logic
            let totalVolumeRef = 0;
            if (data.volumes) {
                data.volumes.forEach(item => {
                    totalVolumeRef += convertAmount(item.volume, item.currency, currency);
                });
            }
            // Use compact notation for stats cards
            document.getElementById('stat-volume').innerText = formatMoney(totalVolumeRef, currency, true);

            // Profit
            let totalFeesRef = 0;
            const profitEl = document.getElementById('stat-profit');
            if (profitEl && data.fees) {
                data.fees.forEach(item => {
                    totalFeesRef += convertAmount(item.fees, item.currency, currency);
                });
                profitEl.innerText = formatMoney(totalFeesRef, currency, true);
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
