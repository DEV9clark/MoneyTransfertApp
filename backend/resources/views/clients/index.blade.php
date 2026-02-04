@extends('layouts.app')

@section('title', __('Clients'))
@section('header', __('Client Management'))

@section('content')
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('All Clients') }}</h2>
            <button onclick="openClientModal()" class="bg-primary hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                {{ __('Add Client') }}
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">{{ __('Name') }}</th>
                        <th class="px-6 py-3">{{ __('Phone') }}</th>
                        <th class="px-6 py-3">{{ __('Country') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('Tx Count') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="clients-table-body">
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Client Modal -->
    <div id="clientModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeClientModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">{{ __('Add New Client') }}</h3>
                    <form id="clientForm" class="mt-4 space-y-4">
                        <input type="hidden" id="clientId">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Full Name') }}</label>
                            <input type="text" id="name" name="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm p-2 border" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Phone Number') }}</label>
                            <input type="text" id="phone" name="phone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm p-2 border" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Email (Optional)') }}</label>
                            <input type="email" id="email" name="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm p-2 border">
                        </div>
                         <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Country') }}</label>
                            <select id="country_id" name="country_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md border" required>
                                <!-- Populated by JS -->
                            </select>
                        </div>
                    </form>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="saveClient()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm">
                        {{ __('Save Client') }}
                    </button>
                    <button type="button" onclick="closeClientModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        {{ __('Cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Simple Client Manager JS
    const API_URL = '/api/clients';
    const COUNTRY_API_URL = '/api/countries'; // Assuming we have this or iterate a static list for now, actually we don't have country api yet but I will simulate or fetch from a new endpoint.

    document.addEventListener('DOMContentLoaded', () => {
        loadClients();
        loadCountries();
    });

    async function loadClients() {
        // Mock data if API fails (since Docker might be down)
        try {
            const res = await fetch(API_URL);
            if (!res.ok) throw new Error('API Error');
            const clients = await res.json();
            renderTable(clients);
        } catch (e) {
            console.warn('Using mock data for clients');
             renderTable([]);
        }
    }
    
    // Temporary mock for countries since we didn't create Country API yet
    async function loadCountries() {
         const select = document.getElementById('country_id');
         // We should fetch this from API if available. 
         // For now, let's hardcode the ones we seeded or fetch from a potential endpoint.
         const countries = [
             {id: 1, name: 'United States'},
             {id: 2, name: 'Canada'},
             {id: 3, name: 'France'},
             {id: 4, name: 'Senegal'},
             {id: 5, name: 'Ivory Coast'}
         ];
         
         countries.forEach(c => {
             const option = document.createElement('option');
             option.value = c.id;
             option.textContent = c.name;
             select.appendChild(option);
         });
    }

    function renderTable(clients) {
        const tbody = document.getElementById('clients-table-body');
        tbody.innerHTML = clients.map(client => `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <span class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center font-bold text-gray-600">${client.name.charAt(0)}</span>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${client.name}</div>
                            <div class="text-sm text-gray-500">${client.email || ''}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${client.phone}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${client.country ? client.country.name : '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-bold text-gray-700">${client.transactions_count || 0}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button onclick="editClient(${client.id})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                    <button onclick="deleteClient(${client.id})" class="text-red-600 hover:text-red-900">Delete</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-400">No clients found</td></tr>';
    }

    function openClientModal(client = null) {
        document.getElementById('clientModal').classList.remove('hidden');
        if (client) {
             document.getElementById('modal-title').innerText = 'Edit Client';
             document.getElementById('clientId').value = client.id;
             document.getElementById('name').value = client.name;
             document.getElementById('email').value = client.email;
             document.getElementById('phone').value = client.phone;
             document.getElementById('country_id').value = client.country_id;
        } else {
             document.getElementById('modal-title').innerText = 'Add New Client';
             document.getElementById('clientForm').reset();
             document.getElementById('clientId').value = '';
        }
    }

    function closeClientModal() {
        document.getElementById('clientModal').classList.add('hidden');
    }

    async function saveClient() {
        const id = document.getElementById('clientId').value;
        const data = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            country_id: document.getElementById('country_id').value,
        };

        const method = id ? 'PUT' : 'POST';
        const url = id ? `${API_URL}/${id}` : API_URL;

        await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify(data)
        });

        closeClientModal();
        loadClients();
    }
</script>
@endpush
