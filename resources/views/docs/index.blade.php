@extends('layouts.admin')

@section('page-title', '系統文件')

@section('content')
    @php
        $labels = ['spec' => '規格文件', 'flow' => '開發流程', 'tech' => '技術分析'];
        $grouped = $docs->groupBy('category');
    @endphp

    <div class="max-w-6xl space-y-8">
        @if ($docs->isEmpty())
            <p class="text-gray-500">目前沒有文件。</p>
        @else
            {{-- 搜尋框 --}}
            <div class="relative max-w-sm">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z" />
                </svg>
                <input id="docs-search" type="search" autocomplete="off" placeholder="搜尋文件…"
                       aria-label="搜尋文件"
                       class="form-control w-full pl-9">
            </div>

            {{-- 分組卡片牆 --}}
            @foreach ($labels as $key => $label)
                @php $items = $grouped->get($key); @endphp
                @if ($items)
                    <section data-group>
                        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            {{ $label }} <span class="text-slate-400">({{ $items->count() }})</span>
                        </h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($items as $doc)
                                <a href="{{ url('docs/'.$doc['name']) }}" target="_blank"
                                   data-search="{{ mb_strtolower($doc['heading'].' '.$doc['name']) }}"
                                   class="block rounded-lg border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-md">
                                    <div class="flex items-start gap-3">
                                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-slate-400" fill="none"
                                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        <span class="font-semibold leading-snug text-slate-800">{{ $doc['heading'] }}</span>
                                    </div>
                                    <div class="mt-3 flex items-center justify-between gap-2 text-xs text-slate-400">
                                        <span class="truncate font-mono">{{ $doc['name'] }}.html</span>
                                        <span class="whitespace-nowrap">{{ $doc['modified'] }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            {{-- 搜尋無結果 --}}
            <p id="docs-empty" class="hidden text-gray-500">找不到符合的文件。</p>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        const docsSearch = document.getElementById('docs-search');
        if (docsSearch) {
            const cards = [...document.querySelectorAll('[data-search]')];
            const sections = [...document.querySelectorAll('[data-group]')];
            const empty = document.getElementById('docs-empty');

            docsSearch.addEventListener('input', () => {
                const q = docsSearch.value.trim().toLowerCase();
                cards.forEach((card) => {
                    card.classList.toggle('hidden', q !== '' && !card.dataset.search.includes(q));
                });

                let anyVisible = false;
                sections.forEach((section) => {
                    const visible = section.querySelectorAll('[data-search]:not(.hidden)').length;
                    section.classList.toggle('hidden', visible === 0);
                    if (visible > 0) {
                        anyVisible = true;
                    }
                });

                empty.classList.toggle('hidden', anyVisible);
            });
        }
    </script>
@endpush
