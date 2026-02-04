<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Money Transfer - @yield('title', 'Dashboard')</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (CDN for Prototype) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#4F46E5', // Indigo 600
                        secondary: '#10B981', // Emerald 500
                        dark: '#111827', // Gray 900
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-800">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <aside class="w-64 bg-dark text-white flex-shrink-0 hidden md:flex flex-col transition-all duration-300">
            <div class="h-16 flex items-center justify-center border-b border-gray-700">
                <span class="text-xl font-bold tracking-wider">TRANSFER<span class="text-primary">PRO</span></span>
            </div>
            
            <nav class="flex-1 px-4 py-6 space-y-2">
                <a href="/dashboard" class="flex items-center px-4 py-3 {{ request()->is('dashboard') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }} rounded-lg transition-colors">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    {{ __('Dashboard') }}
                </a>
                <a href="/clients" class="flex items-center px-4 py-3 {{ request()->is('clients*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }} rounded-lg transition-colors">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    {{ __('Clients') }}
                </a>
                <a href="/transactions" class="flex items-center px-4 py-3 {{ request()->is('transactions*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }} rounded-lg transition-colors">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    {{ __('Transactions') }}
                </a>
                
                <!-- Settings Section -->
                <div class="pt-4 mt-4 border-t border-gray-700">
                    <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ __('Settings') }}</p>
                    <a href="/tariffs" class="flex items-center px-4 py-3 {{ request()->is('tariffs*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }} rounded-lg transition-colors">
                        <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ __('Tariffs & Fees') }}
                    </a>
                </div>
            </nav>

            <div class="p-4 border-t border-gray-700">
                <div class="flex items-center">
                    <div class="bg-primary rounded-full w-10 h-10 flex items-center justify-center text-white font-bold">A</div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-white">{{ auth()->user()->name ?? 'Admin' }}</p>
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-gray-400 hover:text-white transition-colors">{{ __('Logout') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50">
            <!-- Topbar -->
            <header class="bg-white shadow-sm glass-effect sticky top-0 z-30">
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">@yield('header')</h1>
                    <div class="flex items-center space-x-4">
                        <!-- Language Switcher -->
                        <div class="flex items-center space-x-1 bg-gray-100 rounded-lg p-1">
                            <a href="{{ route('lang.switch', 'en') }}" class="px-2 py-1 text-xs font-medium rounded {{ app()->getLocale() == 'en' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900' }}">EN</a>
                            <a href="{{ route('lang.switch', 'fr') }}" class="px-2 py-1 text-xs font-medium rounded {{ app()->getLocale() == 'fr' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900' }}">FR</a>
                        </div>

                        <!-- Currency Selector -->
                        <div class="relative">
                            <select id="currency_selector" onchange="updateCurrency(this.value)" class="block appearance-none bg-indigo-50 border border-indigo-200 text-indigo-900 py-2 px-3 pr-8 rounded-lg leading-tight focus:outline-none focus:bg-white focus:border-primary font-bold text-sm cursor-pointer">
                                <option value="USD">USD ($)</option>
                                <option value="EUR">EUR (€)</option>
                                <option value="XOF">XOF (CFA)</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-indigo-600">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                            </div>
                        </div>

                        <!-- Notification Icon with Dropdown -->
                        <div class="relative" id="notification-container">
                            <button onclick="toggleNotificationDropdown()" class="relative p-2 text-gray-400 hover:text-gray-600 transition-colors">
                                <span id="notification_badge" class="hidden absolute top-1 right-1 h-5 w-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center ring-2 ring-white">0</span>
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            </button>
                            
                            <!-- Notification Dropdown -->
                            <div id="notification-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg ring-1 ring-black ring-opacity-5 z-50">
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Pending Transfers') }}</h3>
                                </div>
                                <div id="notification-list" class="max-h-64 overflow-y-auto">
                                    <p class="px-4 py-3 text-sm text-gray-500">{{ __('Loading...') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
    <script>
        // Global Currency State Management
        const RATES = {
            'USD': 1/600,
            'EUR': 1/650, // Example rate
            'XOF': 1   // Example rate
        };
        
        const SYMBOLS = {
            'USD': '$',
            'EUR': '€',
            'XOF': 'CFA'
        };

        function updateCurrency(currency) {
            localStorage.setItem('selected_currency', currency);
            window.dispatchEvent(new CustomEvent('currencyChanged', { detail: { currency } }));
            // Optional: Reload if deep changes needed, but event based is smoother for dashboard
        }

        // Notification Badge Logic
        async function updateNotificationBadge() {
            try {
                const res = await fetch('/api/transactions/pending-count');
                const data = await res.json();
                const badge = document.getElementById('notification_badge');
                
                if (data.count > 0) {
                    badge.innerText = data.count > 99 ? '99+' : data.count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            } catch (e) {
                console.error('Failed to fetch notifications', e);
            }
        }

        // Init on load
        document.addEventListener('DOMContentLoaded', () => {
            const saved = localStorage.getItem('selected_currency') || 'XOF'; // Default to XOF
            const selector = document.getElementById('currency_selector');
            if (selector) {
                selector.value = saved;
            }

            // Fetch notifications
            updateNotificationBadge();
            // Optional: Poll every 30 seconds
            setInterval(updateNotificationBadge, 30000);
        });

        // Notification Dropdown Toggle
        let notificationDropdownOpen = false;

        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notification-dropdown');
            notificationDropdownOpen = !notificationDropdownOpen;
            
            if (notificationDropdownOpen) {
                dropdown.classList.remove('hidden');
                fetchPendingList();
            } else {
                dropdown.classList.add('hidden');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const container = document.getElementById('notification-container');
            if (container && !container.contains(e.target) && notificationDropdownOpen) {
                notificationDropdownOpen = false;
                document.getElementById('notification-dropdown').classList.add('hidden');
            }
        });

        // Fetch pending transactions list
        async function fetchPendingList() {
            const list = document.getElementById('notification-list');
            list.innerHTML = '<p class="px-4 py-3 text-sm text-gray-500">{{ __("Loading...") }}</p>';
            
            try {
                const res = await fetch('/api/transactions/pending-list');
                const data = await res.json();
                
                if (!data.data || data.data.length === 0) {
                    list.innerHTML = '<p class="px-4 py-8 text-sm text-gray-400 text-center">{{ __("No pending transfers") }}</p>';
                    return;
                }

                list.innerHTML = data.data.map(t => `
                    <div onclick="showTransferModal('${t.reference}')" class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-900">#${t.reference}</p>
                                <p class="text-xs text-gray-500">${t.sender}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-primary">${parseFloat(t.amount).toLocaleString()} ${t.currency}</p>
                                <p class="text-xs text-gray-400">${t.created_at}</p>
                            </div>
                        </div>
                    </div>
                `).join('');
            } catch (e) {
                list.innerHTML = '<p class="px-4 py-3 text-sm text-red-500">Error loading transfers</p>';
            }
        }

        // Show Transfer Modal
        function showTransferModal(reference) {
            // Hide dropdown
            notificationDropdownOpen = false;
            document.getElementById('notification-dropdown').classList.add('hidden');
            
            // Open modal with loading state
            const modal = document.getElementById('transfer-modal');
            const content = document.getElementById('modal-content');
            modal.classList.remove('hidden');
            content.innerHTML = '<p class="text-gray-500 text-center py-8">{{ __("Loading...") }}</p>';
            
            // Fetch details via verifyCode
            fetch('/api/transactions/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ code: reference })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.data) {
                    content.innerHTML = '<p class="text-red-500 text-center py-8">{{ __("Invalid or already processed code.") }}</p>';
                    return;
                }
                const d = data.data;
                content.innerHTML = `
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">{{ __("Reference") }}</p>
                                <p class="text-lg font-bold text-gray-900">#${d.reference}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500 uppercase">{{ __("Amount") }}</p>
                                <p class="text-lg font-bold text-primary">${parseFloat(d.amount).toLocaleString()} ${d.currency}</p>
                            </div>
                        </div>
                        <hr class="border-gray-200">
                        <div>
                            <p class="text-xs text-gray-500 uppercase">{{ __("Sender") }}</p>
                            <p class="text-sm font-medium text-gray-900">${d.sender}</p>
                            <p class="text-xs text-gray-500">${d.sender_phone}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase">{{ __("Recipient") }}</p>
                            <p class="text-sm font-medium text-gray-900">${d.recipient_name || '-'}</p>
                            <p class="text-xs text-gray-500">${d.recipient_phone || '-'}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase">{{ __("Created") }}</p>
                            <p class="text-sm text-gray-700">${d.created_at}</p>
                        </div>
                        <hr class="border-gray-200">
                        <div class="flex gap-3">
                            <button onclick="closeModal()" class="flex-1 py-2 px-4 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">{{ __("Close") }}</button>
                            <button onclick="confirmWithdraw('${d.reference}')" class="flex-1 py-2 px-4 bg-primary text-white rounded-lg hover:bg-indigo-700 transition-colors">{{ __("Confirm Withdrawal") }}</button>
                        </div>
                    </div>
                `;
            })
            .catch(e => {
                content.innerHTML = '<p class="text-red-500 text-center py-8">Error loading details</p>';
            });
        }

        function closeModal() {
            document.getElementById('transfer-modal').classList.add('hidden');
        }

        async function confirmWithdraw(code) {
            try {
                const res = await fetch('/api/transactions/withdraw', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ code })
                });
                const data = await res.json();
                
                if (res.ok) {
                    alert('✅ ' + data.message);
                    closeModal();
                    updateNotificationBadge();
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (e) {
                alert('❌ Error processing withdrawal');
            }
        }
    </script>

    <!-- Transfer Detail Modal -->
    <div id="transfer-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('View Details') }}</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div id="modal-content">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
</body>
</html>
