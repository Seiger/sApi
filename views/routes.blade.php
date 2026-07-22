@extends('sApi::layout')

@section('content')
    <div x-data="{search:''}">
        <section class="grid gap-6 p-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            <div class="s-widget">
                <div class="flex items-center gap-2 mb-3">
                    @svg('tabler-list-tree', 'w-5 h-5 text-blue-600 darkness:text-white/80')
                    <h2 class="s-widget-name">Total routes</h2>
                </div>
                <div class="text-3xl font-semibold text-blue-600 mb-1 darkness:text-white">
                    {{number_format($summary['total'] ?? 0, 0, '.', ' ')}}
                </div>
            </div>

            <div class="s-widget">
                <div class="flex items-center gap-2 mb-3">
                    @svg('tabler-lock', 'w-5 h-5 text-emerald-600 darkness:text-white/80')
                    <h2 class="s-widget-name">Protected routes</h2>
                </div>
                <div class="text-3xl font-semibold text-emerald-600 mb-1 darkness:text-white">
                    {{number_format($summary['protected'] ?? 0, 0, '.', ' ')}}
                </div>
                <span class="text-xs text-slate-500 darkness:text-white/90">Middleware contains “jwt”</span>
            </div>

            <div class="s-widget">
                <div class="flex items-center gap-2 mb-3">
                    @svg('tabler-route', 'w-5 h-5 text-slate-600 darkness:text-white/80')
                    <h2 class="s-widget-name">Public routes</h2>
                </div>
                <div class="text-3xl font-semibold text-slate-800 mb-1 darkness:text-white">
                    {{number_format($summary['public'] ?? 0, 0, '.', ' ')}}
                </div>
            </div>
        </section>

        <section class="px-6 pb-6">
            <div class="rounded-2xl bg-white/70 ring-1 ring-blue-200 p-6 darkness:bg-[#0f2645] darkness:bg-opacity-60 darkness:ring-[#113c6e]">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <div class="flex items-center gap-2 text-slate-800 font-medium text-lg darkness:text-slate-100">
                            @svg('tabler-route', 'w-5 h-5 text-blue-500 darkness:text-sky-400')
                            Configured routes
                        </div>
                        <div class="mt-1 text-xs text-slate-500 darkness:text-slate-400">
                            Base path: <span class="font-mono">{{ $basePath !== '' ? '/' . $basePath : '/' }}</span>
                        </div>
                    </div>
                    <div class="w-full max-w-sm">
                        <input x-model="search" type="text" placeholder="Search path or handler..."
                               class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 darkness:border-slate-700 darkness:bg-[#132a44] darkness:text-slate-100">
                    </div>
                </div>

                <div class="py-3 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-slate-200 darkness:border-slate-700">
                        <tr class="text-left text-sm text-slate-600 darkness:text-slate-300">
                            <th class="pb-3 pr-4 font-medium">Method</th>
                            <th class="pb-3 pr-4 font-medium">Path</th>
                            <th class="pb-3 pr-4 font-medium">Handler</th>
                            <th class="pb-3 pr-4 font-medium">Middleware</th>
                            <th class="pb-3 font-medium">Notes</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 darkness:divide-slate-700">
                        @foreach(($groupedRoutes ?? []) as $prefix => $items)
                            <tr class="text-sm bg-slate-50 darkness:bg-[#132a44]">
                                <td class="py-3 px-3 font-medium text-slate-700 darkness:text-slate-200" colspan="5">
                                    Prefix: <span class="font-mono">{{$prefix}}</span>
                                </td>
                            </tr>
                            @foreach($items as $route)
                                @php($searchable = strtolower(($route['path'] ?? '') . ' ' . ($route['handler'] ?? '')))
                                <tr class="text-sm darkness:text-slate-100" x-show="search === '' || '{{ $searchable }}'.includes(search.toLowerCase())" x-cloak>
                                    <td class="whitespace-nowrap py-3 pr-4">
                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold {{ ($route['method'] ?? '') === 'POST' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-700' }}">
                                            {{$route['method'] ?? ''}}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap py-3 pr-4 font-mono text-sm">{{$route['path'] ?? ''}}</td>
                                    <td class="py-3 pr-4 font-mono text-xs text-slate-700 darkness:text-slate-300">{{$route['handler'] ?? ''}}</td>
                                    <td class="py-3 pr-4 text-xs text-slate-700 darkness:text-slate-300">{{$route['middlewareText'] ?? ''}}</td>
                                    <td class="py-3 text-xs text-slate-700 darkness:text-slate-300">
                                        @if(!empty($route['notes']))
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($route['notes'] as $note)
                                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">{{$note}}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection
