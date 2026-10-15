@extends('layouts.app')

@section('title', 'My Invoices')

@section('content')
@php($billingCurrency = strtoupper((string) \App\Services\SettingService::get('currency', 'NGN', auth()->user()?->university_id)))
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">My Invoices</h1>
        <p class="mt-1 text-sm text-gray-600">Review institutional charges and submit a bank-transfer reference for verification.</p>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Invoice</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Semester</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Due date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $invoice->uuid }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $invoice->semester?->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $invoice->currency ?: $billingCurrency }} {{ \App\Support\Money::fromMinor($invoice->amount_minor !== null ? (int) $invoice->amount_minor : \App\Support\Money::toMinor((string) $invoice->amount)) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                    {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $invoice->status === 'overdue' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $invoice->status === 'waived' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $invoice->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="{{ route('billing.show', $invoice) }}" class="font-medium text-indigo-600 hover:text-indigo-900">
                                    {{ $invoice->status === 'pending' ? 'View / pay' : 'View' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $invoices->links() }}</div>
</div>
@endsection
