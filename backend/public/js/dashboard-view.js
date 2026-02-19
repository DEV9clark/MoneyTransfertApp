document.addEventListener('DOMContentLoaded', async () => {
    const api = new DashboardApi('/money_transfer_app/');
    let transactionsData = [];

    // Currency Logic
    let currentCurrency = localStorage.getItem('selected_currency') || 'USD';
    const rateMap = typeof RATES !== 'undefined' ? RATES : { 'USD': 1, 'EUR': 0.92, 'XOF': 600 };
    const symbolMap = typeof SYMBOLS !== 'undefined' ? SYMBOLS : { 'USD': '$', 'EUR': '€', 'XOF': 'CFA' };

    function formatMoney(amount) {
        const rate = rateMap[currentCurrency] || 1;
        const symbol = symbolMap[currentCurrency] || '$';
        const converted = amount * rate;

        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: currentCurrency === 'XOF' ? 'XOF' : (currentCurrency === 'EUR' ? 'EUR' : 'USD')
        }).format(converted);
    }

    async function renderDashboard() {
        if (!transactionsData.length) return;

        // Total Volume
        const totalVolume = transactionsData.reduce((acc, t) => acc + parseFloat(t.amount), 0);
        document.getElementById('stat-volume').innerText = formatMoney(totalVolume);

        // Render Table
        const tableBody = document.getElementById('transactions-table-body');
        tableBody.innerHTML = '';

        transactionsData.slice(0, 5).forEach(t => { // Show last 5
            const row = `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">#${t.reference || 'N/A'}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        ${t.client ? t.client.name : 'Unknown'}
                        <span class="block text-xs text-gray-400">${t.client ? t.client.phone : ''}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 capitalize">${t.type}</td>
                    <td class="px-6 py-4 text-sm font-bold text-gray-900 text-right">${formatMoney(t.amount)}</td>
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
        // Re-fetch stats with new currency (keeping current filter)
        // We need to know the current filter. Let's store it globally or read from active button.
        // For simplicity, default to 'day' or find active. 
        // Better: let's track activeFilter variable.
        const activeFilter = document.querySelector('button[id^="filter-"].bg-white')?.id.replace('filter-', '') || 'day';
        fetchStats(activeFilter);

        // Also re-render table if needed (amount formatting)
        renderDashboard();
    });

    // Helper to fetch stats
    async function fetchStats(filter = 'day') {
        try {
            // Get current currency from state or localstorage
            const currency = localStorage.getItem('selected_currency') || 'USD';

            const res = await fetch(`/money_transfer_app/api/transactions/stats?filter=${filter}&currency=${currency}`, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });
            const data = await res.json();

            // Update Volume
            document.getElementById('stat-volume').innerText = formatMoney(data.volume) + ' ' + currency;

            // Update Profit (if exists)
            const profitEl = document.getElementById('stat-profit');
            if (profitEl) {
                // If the data.fees is in the requested currency, just display it.
                // Note: The API sums the fee_amount for transactions of this currency.
                profitEl.innerText = formatMoney(data.fees) + ' ' + currency;
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
