@extends('layouts.app')

@section('title', 'Tariff Management')
@section('header', 'Tariff Settings')

@section('content')
<div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Current Tariffs</h2>
        @if(auth()->user()->role === 'admin')
        <button onclick="openTariffModal()" class="bg-primary hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add Tariff Range
        </button>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="w-full whitespace-nowrap">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="px-6 py-3">Min Amount</th>
                    <th class="px-6 py-3">Max Amount</th>
                    <th class="px-6 py-3">Type</th>
                    <th class="px-6 py-3">Fee Amount</th>
                    @if(auth()->user()->role === 'admin')
                    <th class="px-6 py-3 text-right">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="tariff-table-body">
                @foreach($tariffs as $tariff)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($tariff->min_amount, 0) }} XOF</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $tariff->max_amount ? number_format($tariff->max_amount, 0) . ' XOF' : '∞' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">{{ $tariff->type }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                        {{ number_format($tariff->fee_amount, 0) }} XOF
                    </td>
                    @if(auth()->user()->role === 'admin')
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button onclick='editTariff(@json($tariff))' class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                        <button onclick="deleteTariff({{ $tariff->id }})" class="text-red-600 hover:text-red-900">Delete</button>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
@if(auth()->user()->role === 'admin')
<div id="tariffModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeTariffModal()"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Tariff Details</h3>
                <form id="tariffForm" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" id="tariffId" name="id">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Min Amount</label>
                            <input type="number" id="min_amount" name="min_amount" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm border p-2" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Amount</label>
                            <input type="number" id="max_amount" name="max_amount" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm border p-2">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Transaction Type</label>
                        <select id="type" name="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm border p-2">
                            <option value="send">Send Money</option>
                            <option value="withdraw">Withdraw Money</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fee Amount (XOF)</label>
                        <input type="number" step="0.01" id="fee_amount" name="fee_amount" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm border p-2" required>
                    </div>
                </form>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="saveTariff()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Save</button>
                <button type="button" onclick="closeTariffModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openTariffModal() {
        document.getElementById('tariffModal').classList.remove('hidden');
        document.getElementById('tariffForm').reset();
        document.getElementById('tariffId').value = '';
    }

    function editTariff(tariff) {
        document.getElementById('tariffModal').classList.remove('hidden');
        document.getElementById('tariffId').value = tariff.id;
        document.getElementById('min_amount').value = tariff.min_amount;
        document.getElementById('max_amount').value = tariff.max_amount;
        document.getElementById('type').value = tariff.type;
        document.getElementById('fee_amount').value = tariff.fee_amount;
    }

    function closeTariffModal() {
        document.getElementById('tariffModal').classList.add('hidden');
    }

    async function saveTariff() {
        const id = document.getElementById('tariffId').value;
        const formData = new FormData(document.getElementById('tariffForm'));
        const data = Object.fromEntries(formData.entries());
        
        try {
            const url = id ? `/tariffs/${id}` : '/tariffs';
            const method = id ? 'PUT' : 'POST';
            
            const res = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });

            if(res.ok) {
                window.location.reload();
            } else {
                alert('Error saving tariff');
            }
        } catch(e) { console.error(e); }
    }

    async function deleteTariff(id) {
        if(!confirm('Are you sure?')) return;
        try {
            await fetch(`/tariffs/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            window.location.reload();
        } catch(e) { console.error(e); }
    }
</script>
@endif

@endsection
