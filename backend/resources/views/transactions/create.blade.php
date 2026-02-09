
@extends('layouts.app')

@section('title', 'New Transaction')
@section('header', 'Process Transaction')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm overflow-hidden p-8">
        <form id="transactionForm">
            <!-- 1. Transaction Type Selector -->
            <div class="mb-8">
                <label class="block text-gray-700 text-sm font-bold mb-2">Select Transaction Type</label>
                <div class="relative">
                    <select id="transaction_type" name="type" onchange="toggleTransactionMode()" class="block appearance-none w-full bg-indigo-50 border border-indigo-200 text-indigo-900 py-4 px-4 pr-8 rounded-xl leading-tight focus:outline-none focus:bg-white focus:border-primary font-bold text-lg">
                        <option value="send">Send Money (Envoi)</option>
                        <option value="withdraw">Withdraw Money (Retrait)</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-indigo-600">
                        <svg class="fill-current h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                    </div>
                </div>
            </div>

            <!-- ================= SEND MODE ================= -->
            <div id="send_mode_section">
                <!-- Client Search / Select -->
                <div class="mb-6 relative">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Sender (Client)</label>
                    <input type="text" id="client_search" class="appearance-none block w-full bg-gray-50 text-gray-700 border border-gray-300 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-primary" placeholder="Search Client by Name or Phone..." autocomplete="off">
                    <input type="hidden" id="client_name" name="client_name">
                    <input type="hidden" id="client_phone" name="client_phone">
                    
                    <!-- Autocomplete Dropdown -->
                    <div id="client_results" class="absolute z-10 bg-white shadow-lg rounded-b-lg w-full border border-gray-200 hidden max-h-60 overflow-y-auto"></div>
                </div>
                
                <!-- Selected Client Info -->
                <div id="selected_client_info" class="mb-6 p-4 bg-green-50 rounded-lg hidden flex justify-between items-center border border-green-200">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-full mr-3 text-green-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <div>
                            <p class="font-bold text-green-900" id="info_name"></p>
                            <p class="text-green-700 text-sm" id="info_phone"></p>
                        </div>
                    </div>
                    <button type="button" onclick="clearClient()" class="text-sm font-medium text-green-600 hover:text-green-800 underline">Change</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                        <div class="relative">
                            <input class="appearance-none block w-full bg-gray-50 text-gray-700 border border-gray-300 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-primary font-mono text-lg" id="amount" name="amount" type="number" placeholder="0.00" oninput="calculateFees()">
                            <div class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 font-bold">XOF</div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Total to Pay</label>
                        <div class="relative">
                            <input class="appearance-none block w-full bg-gray-100 text-gray-900 border border-gray-300 rounded py-3 px-4 leading-tight font-bold text-lg" id="total_amount" type="text" readonly value="0">
                            <div class="absolute inset-y-0 right-0 flex items-center px-3">
                                <span class="text-xs text-white bg-indigo-500 rounded px-2 py-1" id="fee_display">Fee: 0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Destination Country</label>
                    <select id="destination" name="destination" class="block appearance-none w-full bg-gray-50 border border-gray-300 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-primary">
                        <option value="Senegal">Senegal</option>
                        <option value="Ivory Coast">Ivory Coast</option>
                        <option value="Mali">Mali</option>
                        <option value="To Be Defined">Other</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Recipient Name</label>
                        <input class="appearance-none block w-full bg-gray-50 text-gray-700 border border-gray-300 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-primary" id="recipient_name" name="recipient_name" type="text" placeholder="Receiver's Full Name">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Recipient Phone</label>
                        <input class="appearance-none block w-full bg-gray-50 text-gray-700 border border-gray-300 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-primary" id="recipient_phone" name="recipient_phone" type="text" placeholder="+221 ...">
                    </div>
                </div>
            </div>
            
            <!-- ================= WITHDRAW MODE ================= -->
            <div id="withdraw_mode_section" class="hidden">
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Ask the client for the transaction code received via SMS.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-gray-700 text-lg font-bold mb-4 text-center">Enter Transaction Code</label>
                    <div class="flex justify-center">
                        <input type="text" id="withdraw_code" name="code" class="block w-64 text-center text-3xl font-mono tracking-widest border-2 border-gray-300 rounded-xl py-4 focus:outline-none focus:border-primary focus:ring-4 focus:ring-indigo-100 uppercase" placeholder="ABC-123" maxlength="10">
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-center pt-4 border-t border-gray-100">
                <button type="submit" id="submit_btn" class="w-full bg-primary hover:bg-indigo-700 text-white font-bold py-4 px-8 rounded-xl focus:outline-none focus:shadow-outline transition-all transform hover:-translate-y-1 shadow-lg text-lg">
                    Process Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Verification Modal -->
<div id="verifyModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black bg-opacity-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden transform transition-all scale-95 opacity-0" id="verifyModalContent">
        <div class="bg-indigo-600 p-6 text-white text-center">
            <h3 class="text-2xl font-bold">Confirm Withdrawal</h3>
            <p class="text-indigo-100 mt-1">Please verify details with the client</p>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-500">Amount</span>
                <span class="font-bold text-xl text-gray-800" id="verify_amount"></span>
            </div>
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-500">Sender</span>
                <div class="text-right">
                    <div class="font-bold text-gray-800" id="verify_sender"></div>
                    <div class="text-sm text-gray-500" id="verify_sender_phone"></div>
                </div>
            </div>
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-500">Recipient</span>
                <div class="text-right">
                    <div class="font-bold text-gray-800" id="verify_recipient"></div>
                    <div class="text-sm text-gray-500" id="verify_recipient_phone"></div>
                </div>
            </div>
            <div class="flex justify-between pb-2">
                <span class="text-gray-500">Date</span>
                <span class="font-bold text-gray-800" id="verify_date"></span>
            </div>
        </div>
        <div class="p-6 bg-gray-50 flex space-x-4">
            <button onclick="closeModal()" class="w-1/2 bg-gray-200 text-gray-800 font-bold py-3 rounded-xl hover:bg-gray-300 transition">
                Cancel
            </button>
            <button onclick="confirmWithdrawal()" class="w-1/2 bg-green-500 text-white font-bold py-3 rounded-xl hover:bg-green-600 transition shadow-lg">
                Confirm & Pay
            </button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 -translate-y-20 opacity-0">
    <div id="toast_content" class="bg-gray-800 text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3">
        <span id="toast_icon"></span>
        <p id="toast_message" class="font-medium"></p>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let searchTimeout;

    // Toast Function
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const content = document.getElementById('toast_content');
        const msg = document.getElementById('toast_message');
        const icon = document.getElementById('toast_icon');
        
        msg.innerText = message;
        
        if (type === 'success') {
            content.className = 'bg-green-600 text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3';
            icon.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        } else if (type === 'error') {
            content.className = 'bg-red-600 text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3';
            icon.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
        }

        // Show: Remove hidings, add visibility
        toast.classList.remove('-translate-y-20', 'opacity-0');
        
        setTimeout(() => {
            // Hide: Add hidings
            toast.classList.add('-translate-y-20', 'opacity-0');
        }, 3000);
    }

    function toggleTransactionMode() {
        const type = document.getElementById('transaction_type').value;
        const btn = document.getElementById('submit_btn');

        if (type === 'send') {
            document.getElementById('send_mode_section').classList.remove('hidden');
            document.getElementById('withdraw_mode_section').classList.add('hidden');
            btn.innerText = 'Process Transfer';
            btn.classList.add('bg-primary');
            btn.classList.remove('bg-yellow-500', 'hover:bg-yellow-600');
            btn.onclick = null; // Reset default submit behavior
        } else {
            document.getElementById('send_mode_section').classList.add('hidden');
            document.getElementById('withdraw_mode_section').classList.remove('hidden');
            btn.innerText = 'Check Code';
            btn.classList.remove('bg-primary', 'hover:bg-indigo-700');
            btn.classList.add('bg-yellow-500', 'hover:bg-yellow-600');
        }
    }

    // Modal Logic
    const modal = document.getElementById('verifyModal');
    const modalContent = document.getElementById('verifyModalContent');

    function openModal(data) {
        document.getElementById('verify_amount').innerText = new Intl.NumberFormat('fr-FR').format(data.amount) + ' ' + data.currency;
        document.getElementById('verify_sender').innerText = data.sender;
        document.getElementById('verify_sender_phone').innerText = data.sender_phone;
        document.getElementById('verify_recipient').innerText = data.recipient_name || 'N/A';
        document.getElementById('verify_recipient_phone').innerText = data.recipient_phone || 'N/A';
        document.getElementById('verify_date').innerText = data.created_at;
        
        modal.classList.remove('hidden');
        // Small delay for animation
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeModal() {
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    async function confirmWithdrawal() {
        const code = document.getElementById('withdraw_code').value;
        closeModal();
        
        try {
             const res = await fetch("{{ url('/api/transactions/withdraw') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ code })
            });

            const result = await res.json();
            if (!res.ok) throw new Error(result.message);

            showToast('Withdrawal Confirmed! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = '/dashboard';
            }, 1500);

        } catch (error) {
            showToast(error.message, 'error');
        }
    }

    // Client Autocomplete Logic
    document.getElementById('client_search').addEventListener('input', function(e) {
        const query = e.target.value;
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            document.getElementById('client_results').classList.add('hidden');
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const res = await fetch(`{{ url('/api/clients/search') }}?query=${query}`);
                const clients = await res.json();
                
                const resultsDiv = document.getElementById('client_results');
                resultsDiv.innerHTML = '';
                
                if (clients.length > 0) {
                    resultsDiv.classList.remove('hidden');
                    clients.forEach(client => {
                        const div = document.createElement('div');
                        div.className = 'p-3 hover:bg-gray-100 cursor-pointer border-b last:border-b-0';
                        div.innerHTML = `<p class="font-bold">${client.name}</p><p class="text-sm text-gray-500">${client.phone}</p>`;
                        div.onclick = () => selectClient(client);
                        resultsDiv.appendChild(div);
                    });
                } else {
                    resultsDiv.classList.add('hidden');
                }
            } catch (e) { console.error(e); }
        }, 300);
    });

    function selectClient(client) {
        document.getElementById('client_name').value = client.name;
        document.getElementById('client_phone').value = client.phone;
        
        document.getElementById('info_name').innerText = client.name;
        document.getElementById('info_phone').innerText = client.phone;
        
        document.getElementById('client_search').classList.add('hidden');
        document.getElementById('client_results').classList.add('hidden');
        document.getElementById('selected_client_info').classList.remove('hidden');
    }

    function clearClient() {
        document.getElementById('client_name').value = '';
        document.getElementById('client_phone').value = '';
        document.getElementById('client_search').value = '';
        
        document.getElementById('client_search').classList.remove('hidden');
        document.getElementById('selected_client_info').classList.add('hidden');
    }

    // Fee Calculation
    async function calculateFees() {
        if (document.getElementById('transaction_type').value !== 'send') return;

        const amount = document.getElementById('amount').value;
        const type = 'send';

        if (!amount || amount <= 0) {
            document.getElementById('total_amount').value = '0';
            document.getElementById('fee_display').innerText = 'Fee: 0';
            return;
        }

        try {
            const res = await fetch("{{ url('/api/transactions/calculate-fees') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ amount, type })
            });
            
            const data = await res.json();
            document.getElementById('total_amount').value = new Intl.NumberFormat('fr-FR').format(data.total);
            document.getElementById('fee_display').innerText = `Fee: ${new Intl.NumberFormat('fr-FR').format(data.fee)}`;
        } catch (e) {
            console.error(e);
        }
    }

    // Main Form Submit Interception
    document.getElementById('transactionForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const type = document.getElementById('transaction_type').value;
        const btn = document.getElementById('submit_btn');
        const originalText = btn.innerText;
        
        btn.disabled = true;
        btn.innerText = 'Processing...';

        try {
            if (type === 'send') {
                 // Send Logic (Same as before but with Toast)
                if (!document.getElementById('client_name').value) {
                     const searchVal = document.getElementById('client_search').value;
                     if (searchVal) {
                        document.getElementById('client_name').value = searchVal;
                        if (!document.getElementById('client_phone').value) {
                             const p = prompt("Enter client phone:"); // Keep confirm for this edge case or switch to modal? prompt is blocking but simple.
                             if(!p) throw new Error("Client phone required.");
                             document.getElementById('client_phone').value = p;
                        }
                     } else {
                        throw new Error("Please select a client.");
                     }
                }

                const formData = {
                    type: 'send',
                    client_name: document.getElementById('client_name').value,
                    client_phone: document.getElementById('client_phone').value,
                    amount: document.getElementById('amount').value,
                    destination: document.getElementById('destination').value,
                    recipient_name: document.getElementById('recipient_name').value,
                    recipient_phone: document.getElementById('recipient_phone').value,
                };

                const res = await fetch("{{ url('/api/transactions') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(formData)
                });
                
                const result = await res.json();
                if (!res.ok) throw new Error(result.message);
                
                showToast('Transfer Successful! Ref: ' + result.code, 'success');
                setTimeout(() => window.location.reload(), 2000);

            } else {
                // Withdraw CHECK Logic
                const code = document.getElementById('withdraw_code').value;
                if (!code) throw new Error("Please enter the transaction code.");

                // Call Verify Endpoint
                const res = await fetch("{{ url('/api/transactions/verify') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ code })
                });

                const result = await res.json();
                if (!res.ok) throw new Error(result.message);

                // Show Modal on Success
                openModal(result.data);
                
                // Re-enable button
                btn.disabled = false;
                btn.innerText = originalText;
            }

        } catch (error) {
            showToast(error.message, 'error');
            btn.disabled = false;
            btn.innerText = originalText;
        }
    });
</script>
@endpush
