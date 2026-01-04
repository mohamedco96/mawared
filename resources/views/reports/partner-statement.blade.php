@extends('layouts.print')

@section('title', 'كشف حساب عميل')

@section('header')
    <div class="invoice-header">
        <div class="header-grid">
            {{-- Company Section --}}
            <div class="company-section">
                <h1 class="company-name">{{ $companySettings->company_name }}</h1>
                <div class="company-details">
                    <p>{{ $companySettings->company_address }}</p>
                    <p>هاتف: {{ $companySettings->company_phone }}</p>
                </div>
            </div>

            {{-- Report Title Box --}}
            <div class="invoice-info-box">
                <h2 class="invoice-title">كشف حساب عميل</h2>
                <div class="invoice-meta">
                    <div><strong>الفترة:</strong> من {{ $reportData['from_date'] }} إلى {{ $reportData['to_date'] }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    {{-- Partner Information --}}
    <div class="client-section">
        <h3 class="section-title">بيانات العميل</h3>
        <div class="client-details">
            <div><strong>اسم العميل:</strong> {{ $reportData['partner']->name }}</div>
            @if ($reportData['partner']->phone)
                <div><strong>الهاتف:</strong> {{ $reportData['partner']->phone }}</div>
            @endif
        </div>
    </div>

    {{-- Transactions Table --}}
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width: 12%;">التاريخ</th>
                <th style="width: 15%;">رقم المرجع</th>
                <th style="width: 33%;" class="hide-on-thermal">البيان</th>
                <th style="width: 13%;">مدين</th>
                <th style="width: 13%;">دائن</th>
                <th style="width: 14%;">الرصيد</th>
            </tr>
        </thead>
        <tbody>
            {{-- Opening Balance Row --}}
            <tr style="background-color: #fef3c7;">
                <td colspan="{{ $format === 'thermal' ? '5' : '6' }}" style="font-weight: bold; text-align: right;">رصيد أول المدة</td>
                <td style="font-weight: bold; text-align: center;">
                    {{ number_format($reportData['opening_balance'], 2) }}
                </td>
            </tr>

            {{-- Transaction Rows --}}
            @foreach ($reportData['transactions'] as $transaction)
                <tr>
                    <td style="text-align: center;">{{ $transaction['date'] }}</td>
                    <td style="text-align: center;">{{ $transaction['reference'] }}</td>
                    <td class="col-product hide-on-thermal">{{ $transaction['description'] }}</td>
                    <td style="text-align: center;">
                        {{ $transaction['debit'] > 0 ? number_format($transaction['debit'], 2) : '-' }}
                    </td>
                    <td style="text-align: center;">
                        {{ $transaction['credit'] > 0 ? number_format($transaction['credit'], 2) : '-' }}
                    </td>
                    <td style="text-align: center; {{ $transaction['balance'] < 0 ? 'color: red;' : ($transaction['balance'] > 0 ? 'color: green;' : '') }}">
                        {{ number_format($transaction['balance'], 2) }}
                    </td>
                </tr>
            @endforeach

            {{-- Totals Row --}}
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="{{ $format === 'thermal' ? '2' : '3' }}" style="text-align: right;">الإجمالي:</td>
                <td style="text-align: center;">{{ number_format($reportData['total_debit'], 2) }}</td>
                <td style="text-align: center;">{{ number_format($reportData['total_credit'], 2) }}</td>
                <td style="text-align: center; {{ $reportData['closing_balance'] < 0 ? 'color: red;' : ($reportData['closing_balance'] > 0 ? 'color: green;' : '') }}">
                    {{ number_format($reportData['closing_balance'], 2) }}
                </td>
            </tr>
        </tbody>
    </table>
@endsection

@section('footer')
    <div class="print-date">تم الطباعة بتاريخ: {{ now()->format('Y-m-d H:i') }}</div>

    {{-- Manual Print Button (hidden during actual print) --}}
    <button class="btn-print no-print">طباعة</button>
@endsection
