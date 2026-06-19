@extends('layouts.admin')

@section('page-title', '建立帳單')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="page-title">建立帳單</h2>
        <a href="{{ route('bills.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← 返回列表</a>
    </div>

    <div class="max-w-3xl mx-auto space-y-6 font-[Noto_Sans_TC,sans-serif]">

        <div class="flash-area"></div>

        <form id="bill-form" method="POST" action="{{ route('bills.store') }}">
            @csrf

            {{-- Vue island: the wizard renders all its UI + hidden inputs inside this form --}}
            @php
                $wizardProps = [
                    'shopSearchUrl' => route('bills.shop-search'),
                    'shopInfoUrl'   => route('bills.shop-info'),
                    'calculateUrl'  => route('bills.calculate'),
                    'today'         => now()->toDateString(),
                    'formAction'    => route('bills.store'),
                    'discounts'     => $discounts->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
                    'userName'      => Auth::user()->name,
                ];
            @endphp
            <div
                id="bill-create-wizard"
                data-props='@json($wizardProps)'
            ></div>

            {{-- Blade validation errors (shown after server-side redirect with errors) --}}
            @if ($errors->any())
                <div class="mt-4 p-3 rounded-lg bg-red-50 text-sm text-red-600">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </form>

    </div>
@endsection

@push('scripts')
    @vite('resources/js/bills/create.js')
@endpush
