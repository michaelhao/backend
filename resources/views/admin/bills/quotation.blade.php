<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<style>
    @font-face {
        font-family: 'WQY';
        src: url('{{ storage_path("fonts/wqy-microhei.ttf") }}') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
    * { margin: 0; padding: 0; }
    body { font-family: 'WQY', 'DejaVu Sans', sans-serif; font-size: 13px; color: #1f2937; padding: 40px; }
    h1 { font-size: 22px; font-weight: normal; margin-bottom: 4px; }
    .sub-title { font-size: 12px; color: #6b7280; margin-bottom: 28px; }
    .meta-grid { display: table; width: 100%; margin-bottom: 28px; }
    .meta-row { display: table-row; }
    .meta-label { display: table-cell; width: 80px; color: #6b7280; padding-bottom: 5px; }
    .meta-value { display: table-cell; padding-bottom: 5px; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    table.items thead tr { background: #f3f4f6; }
    table.items th { padding: 8px 10px; text-align: left; font-size: 11px; color: #6b7280; font-weight: normal; border-bottom: 1px solid #e5e7eb; }
    table.items th.right { text-align: right; }
    table.items td { padding: 9px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    table.items td.right { text-align: right; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
    .badge-1 { background: #dbeafe; color: #1d4ed8; }
    .badge-2 { background: #ede9fe; color: #6d28d9; }
    .badge-3 { background: #d1fae5; color: #065f46; }
    .badge-4 { background: #ffedd5; color: #9a3412; }
    table.totals { width: 260px; float: right; border-collapse: collapse; }
    table.totals td { padding: 4px 0; color: #6b7280; }
    table.totals td.amount { text-align: right; }
    table.totals tr.total td { color: #111827; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 14px; }
    .clearfix { clear: both; }
    .footer { margin-top: 50px; font-size: 11px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

<h1>報價單</h1>
<div class="sub-title">Quotation</div>

<div class="meta-grid">
    <div class="meta-row">
        <div class="meta-label">帳單編號</div>
        <div class="meta-value">{{ $bill->no }}</div>
    </div>
    <div class="meta-row">
        <div class="meta-label">商店</div>
        <div class="meta-value">{{ $bill->shop->name }}</div>
    </div>
    <div class="meta-row">
        <div class="meta-label">日期</div>
        <div class="meta-value">{{ now()->format('Y-m-d') }}</div>
    </div>
</div>

<table class="items">
    <thead>
        <tr>
            <th>項目名稱</th>
            <th>類型</th>
            <th>起始日</th>
            <th>到期日</th>
            <th class="right">總價</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($details as $d)
        <tr>
            <td>{{ $d['name'] }}</td>
            <td><span class="badge badge-{{ $d['type'] }}">{{ $d['type_label'] }}</span></td>
            <td>{{ $d['type'] === 4 ? '' : ($d['start_at'] ?? '—') }}</td>
            <td>{{ $d['type'] === 4 ? '' : ($d['expired_at'] ?? '—') }}</td>
            <td class="right">NT${{ number_format($d['total_price']) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@php $subtotal = $bill->total_grade + $bill->total_addons; @endphp
<table class="totals">
    <tr>
        <td>小計</td>
        <td class="amount">NT${{ number_format($subtotal) }}</td>
    </tr>
    @if ($bill->discount_amount > 0)
    <tr>
        <td>折抵</td>
        <td class="amount">NT${{ number_format($bill->discount_amount) }}</td>
    </tr>
    @endif
    <tr class="total">
        <td>總金額</td>
        <td class="amount">NT${{ number_format($bill->total) }}</td>
    </tr>
</table>
<div class="clearfix"></div>

<div class="footer">本報價單由系統自動產生</div>

</body>
</html>
