@extends('portal.layout')

@section('title', 'Account portal — '.$customer->name)

@section('content')
<div class="card">
    <div class="hero">
        <h1>{{ $company['name'] ?? config('app.name') }}</h1>
        <p>Welcome, {{ $customer->name }}</p>
    </div>
    <div class="body">
        <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <div>
                <div class="muted">Outstanding balance</div>
                <div class="stat">{{ number_format($open_balance, 2) }} {{ $currency }}</div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="{{ $statement_url }}" class="btn btn-secondary">Download statement (PDF)</a>
            </div>
        </div>

        <h2 style="font-size:1rem;margin:0 0 12px;">Recent invoices</h2>
        @if($invoices->isEmpty())
            <p class="muted">No invoices on file yet.</p>
        @else
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Balance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice['invoice_number'] }}</td>
                            <td>{{ $invoice['issue_date'] }}</td>
                            <td>{{ $invoice['due_date'] ?? '—' }}</td>
                            <td>{{ ucfirst($invoice['status']) }}</td>
                            <td class="text-right">{{ number_format($invoice['total_amount'], 2) }}</td>
                            <td class="text-right">{{ number_format($invoice['balance'], 2) }}</td>
                            <td class="text-right" style="white-space:nowrap;">
                                <a href="{{ $invoice['view_url'] }}" class="btn btn-secondary" style="padding:6px 12px;font-size:.8rem;">View</a>
                                @if($invoice['pay_url'])
                                <a href="{{ $invoice['pay_url'] }}" class="btn btn-primary" style="padding:6px 12px;font-size:.8rem;">Pay Now</a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
