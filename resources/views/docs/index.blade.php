@extends('layouts.admin')

@section('page-title', '系統文件')

@section('content')
    <div class="max-w-2xl">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">系統文件</h2>

            @if ($docs->isEmpty())
                <p class="text-gray-500">目前沒有文件。</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($docs as $doc)
                        <li>
                            <a href="{{ route('docs.show', $doc['name']) }}" target="_blank"
                               class="flex items-center justify-between gap-4 py-3 px-2 rounded hover:bg-gray-50 transition-colors">
                                <span class="text-blue-600 font-medium">{{ $doc['title'] }}</span>
                                <span class="text-xs text-gray-400 whitespace-nowrap">{{ $doc['name'] }}.html</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
