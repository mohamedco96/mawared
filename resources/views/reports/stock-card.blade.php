@extends('layouts.print')

@section('title', 'كارت الصنف')

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
                <h2 class="invoice-title">كارت الصنف</h2>
                <div class="invoice-meta">
                    <div><strong>الفترة:</strong> من {{ $reportData['from_date'] }} إلى {{ $reportData['to_date'] }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    {{-- Product Information --}}
    <div class="client-section">
        <h3 class="section-title">بيانات المنتج</h3>
        <div class="client-details">
            <div><strong>اسم المنتج:</strong> {{ $reportData['product']->name }}</div>
            <div><strong>كود المنتج:</strong> {{ $reportData['product']->sku }}</div>
            <div><strong>المخزن:</strong> {{ $reportData['warehouse'] ? $reportData['warehouse']->name : 'جميع المخازن' }}</div>
        </div>
    </div>

    {{-- Stock Movements Table --}}
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width: 10%;">التاريخ</th>
                <th style="width: 12%;" class="hide-on-thermal">النوع</th>
                <th style="width: 12%;">رقم المرجع</th>
                <th style="width: 15%;" class="hide-on-thermal">المخزن</th>
                <th style="width: 10%;">وارد</th>
                <th style="width: 10%;">صادر</th>
                <th style="width: 12%;" class="hide-on-thermal">التكلفة</th>
                <th style="width: 12%;">الرصيد</th>
            </tr>
        </thead>
        <tbody>
            {{-- Opening Stock Row --}}
            <tr style="background-color: #fef3c7;">
                <td colspan="{{ $format === 'thermal' ? '3' : '7' }}" style="font-weight: bold; text-align: right;">رصيد أول المدة</td>
                <td style="font-weight: bold; text-align: center;">
                    {{ number_format($reportData['opening_stock'], 0) }}
                </td>
            </tr>

            {{-- Movement Rows --}}
            @foreach ($reportData['movements'] as $movement)
                <tr>
                    <td style="text-align: center;">{{ $movement['date'] }}</td>
                    <td class="hide-on-thermal" style="text-align: center;">{{ $movement['type'] }}</td>
                    <td style="text-align: center;">{{ $movement['reference'] }}</td>
                    <td class="col-product hide-on-thermal">{{ $movement['warehouse'] }}</td>
                    <td style="text-align: center; {{ $movement['in'] > 0 ? 'color: green;' : '' }}">
                        {{ $movement['in'] > 0 ? number_format($movement['in'], 0) : '-' }}
                    </td>
                    <td style="text-align: center; {{ $movement['out'] > 0 ? 'color: red;' : '' }}">
                        {{ $movement['out'] > 0 ? number_format($movement['out'], 0) : '-' }}
                    </td>
                    <td class="hide-on-thermal" style="text-align: center;">
                        {{ $movement['cost'] > 0 ? number_format($movement['cost'], 2) : '-' }}
                    </td>
                    <td style="text-align: center; font-weight: bold;">
                        {{ number_format($movement['balance'], 0) }}
                    </td>
                </tr>
            @endforeach

            {{-- Totals Row --}}
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="{{ $format === 'thermal' ? '2' : '4' }}" style="text-align: right;">الإجمالي:</td>
                <td style="text-align: center; color: green;">{{ number_format($reportData['total_in'], 0) }}</td>
                <td style="text-align: center; color: red;">{{ number_format($reportData['total_out'], 0) }}</td>
                <td colspan="{{ $format === 'thermal' ? '1' : '2' }}" style="text-align: center;">{{ number_format($reportData['closing_stock'], 0) }}</td>
            </tr>
        </tbody>
    </table>
@endsection

@section('footer')
    <div class="print-date">تم الطباعة بتاريخ: {{ now()->format('Y-m-d H:i') }}</div>

    {{-- Manual Print Button (hidden during actual print) --}}
    <button class="btn-print no-print">طباعة</button>
@endsection
