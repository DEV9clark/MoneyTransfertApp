@extends('layouts.app')

@section('title', __('Dashboard'))
@section('header', __('Dashboard'))

@section('content')
    <!-- Stats Grid -->
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-xl font-bold text-gray-800">{{ __('Overview') }}</h2>
        <div class="inline-flex bg-gray-100 rounded-lg p-1">
            <button onclick="changeFilter('day')" id="filter-day" class="px-4 py-2 rounded-md text-sm font-medium bg-white text-gray-800 shadow-sm transition-all">{{ __('Day') }}</button>
            <button onclick="changeFilter('week')" id="filter-week" class="px-4 py-2 rounded-md text-sm font-medium text-gray-500 hover:text-gray-900 transition-all">{{ __('Week') }}</button>
            <button onclick="changeFilter('month')" id="filter-month" class="px-4 py-2 rounded-md text-sm font-medium text-gray-500 hover:text-gray-900 transition-all">{{ __('Month') }}</button>
            <button onclick="changeFilter('year')" id="filter-year" class="px-4 py-2 rounded-md text-sm font-medium text-gray-500 hover:text-gray-900 transition-all">{{ __('Year') }}</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Client Count -->
        <div class="bg-white rounded-xl shadow-sm p-6 flex items-center justify-between transform hover:scale-105 transition-transform duration-300">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ __('Total Clients') }}</p>
                <p class="text-3xl font-bold text-gray-900 mt-1" id="stat-clients">-</p>
            </div>
            <div class="p-3 bg-blue-100 rounded-full text-blue-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
        </div>

        <!-- Transaction Volume -->
        <div class="bg-white rounded-xl shadow-sm p-6 flex items-center justify-between transform hover:scale-105 transition-transform duration-300">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ __('Transaction Volume') }}</p>
                <p class="text-3xl font-bold text-gray-900 mt-1" id="stat-volume">-</p>
            </div>
            <div class="p-3 bg-green-100 rounded-full text-green-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <!-- Agents (Visible to all for now) -->
        <div class="bg-white rounded-xl shadow-sm p-6 flex items-center justify-between transform hover:scale-105 transition-transform duration-300">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ __('Active Agents') }}</p>
                <p class="text-3xl font-bold text-gray-900 mt-1" id="stat-agents">-</p>
            </div>
            <div class="p-3 bg-indigo-100 rounded-full text-indigo-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            </div>
        </div>

        @if(auth()->user()->role === 'admin')
        <!-- Profit (Admin Only) -->
        <div class="bg-white rounded-xl shadow-sm p-6 flex items-center justify-between transform hover:scale-105 transition-transform duration-300 border-l-4 border-purple-500">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ __('Total Profit') }}</p>
                <p class="text-3xl font-bold text-purple-900 mt-1" id="stat-profit">-</p>
            </div>
            <div class="p-3 bg-purple-100 rounded-full text-purple-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
            </div>
        </div>
        @endif
    </div>

    <!-- Recent Transactions Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Recent Transactions') }}</h2>
            <button class="text-sm text-primary hover:text-indigo-800 font-medium">{{ __('See All') }}</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">{{ __('Reference') }}</th>
                        <th class="px-6 py-3">{{ __('Client') }}</th>
                        <th class="px-6 py-3">{{ __('Type') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Amount') }}</th>
                        <th class="px-6 py-3">{{ __('Status') }}</th>
                        <th class="px-6 py-3">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="transactions-table-body">
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-400">{{ __('Loading...') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<!-- Dashboard API (Our polished JS) -->
<script src="{{ asset('js/dashboard-api.js') }}"></script>
<!-- Dashboard View Logic -->
<script src="{{ asset('js/dashboard-view.js') }}"></script>
@endpush
