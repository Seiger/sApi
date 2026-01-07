@extends('sApi::layout')

@section('content')
    <div x-data="{search:''}">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-medium text-slate-500">Total routes</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{$summary['total'] ?? 0}}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-medium text-slate-500">Protected routes</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{$summary['protected'] ?? 0}}</div>
                <div class="mt-1 text-xs text-slate-500">Middleware contains “jwt”</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-medium text-slate-500">Public routes</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{$summary['public'] ?? 0}}</div>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-4 py-3">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Configured routes</div>
                    <div class="text-xs text-slate-500">Base path: <span class="font-mono">{{ $basePath !== '' ? '/' . $basePath : '/' }}</span></div>
                </div>
                <div class="w-full max-w-sm">
                    <input x-model="search" type="text" placeholder="Search path or handler..."
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Method</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Path</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Handler</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Middleware</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Notes</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach(($groupedRoutes ?? []) as $prefix => $items)
                        <tr class="bg-slate-50">
                            <td class="px-4 py-2 text-xs font-semibold text-slate-700" colspan="5">
                                Prefix: <span class="font-mono">{{$prefix}}</span>
                            </td>
                        </tr>
                        @foreach($items as $route)
                            @php($searchable = strtolower(($route['path'] ?? '') . ' ' . ($route['handler'] ?? '')))
                            <tr x-show="search === '' || '{{ $searchable }}'.includes(search.toLowerCase())" x-cloak>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold {{ ($route['method'] ?? '') === 'POST' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-700' }}">
                                        {{$route['method'] ?? ''}}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-sm text-slate-900">{{$route['path'] ?? ''}}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">{{$route['handler'] ?? ''}}</td>
                                <td class="px-4 py-3 text-xs text-slate-700">{{$route['middlewareText'] ?? ''}}</td>
                                <td class="px-4 py-3 text-xs text-slate-700">
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
    </div>
@endsection
