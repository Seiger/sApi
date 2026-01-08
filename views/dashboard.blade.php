@extends('sApi::layout')
@section('header')
    <button onclick="window.location.reload()" class="s-btn s-btn--primary">
        @svg('tabler-refresh', 'w-4 h-4') @lang('sApi::global.refresh')
    </button>
@endsection
@section('content')
    <section class="grid gap-6 p-6 grid-cols-1 xs:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
        {{-- Requests Today --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                @svg('tabler-activity-heartbeat', 'w-5 h-5 text-blue-600 darkness:text-white/80')
                <h2 class="s-widget-name">@lang('sApi::global.requests_today')</h2>
            </div>
            <div class="text-3xl font-semibold text-blue-600 mb-1 darkness:text-white">
                {{number_format($stats['requests_today'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">@lang('sApi::global.all_methods')</span>
        </div>

        {{-- Requests Success --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                @svg('tabler-square-check', 'w-5 h-5 text-emerald-600 darkness:text-white/80')
                <h2 class="s-widget-name">@lang('sApi::global.success')</h2>
            </div>
            <div class="text-3xl font-semibold text-emerald-600 mb-1 darkness:text-white">
                {{number_format($stats['success'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">2xx</span>
        </div>

        {{-- Requests Clients --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                @svg('tabler-alert-square', 'w-5 h-5 text-amber-600 darkness:text-white/80')
                <h2 class="s-widget-name">@lang('sApi::global.clients')</h2>
            </div>
            <div class="text-3xl font-semibold text-amber-600 mb-1 darkness:text-white">
                {{number_format($stats['clients'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">4xx</span>
        </div>

        {{-- Requests Servers --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                @svg('tabler-square-x', 'w-5 h-5 text-red-600 darkness:text-white/80')
                <h2 class="s-widget-name">@lang('sApi::global.servers')</h2>
            </div>
            <div class="text-3xl font-semibold text-red-600 mb-1 darkness:text-white">
                {{number_format($stats['servers'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">5xx</span>
        </div>

        {{-- Total Requests --}}
        <div class="s-widget">
            <div class="flex items-center gap-2 mb-3">
                @svg('tabler-list-tree', 'w-5 h-5 text-slate-600 darkness:text-white/80')
                <h2 class="s-widget-name">@lang('sApi::global.total')</h2>
            </div>
            <div class="text-3xl font-semibold text-slate-800 mb-1 darkness:text-white">
                {{number_format($stats['total'] ?? 0, 0, '.', ' ')}}
            </div>
            <span class="text-xs text-slate-500 darkness:text-white/90">@lang('sApi::global.at_cnt_days', ['cnt' => env('LOG_DAILY_DAYS', 14)])</span>
        </div>
    </section>

    {{-- Recent Requests --}}
    <section class="px-6 pb-6">
        <div class="rounded-2xl bg-white/70 ring-1 ring-blue-200 p-6 darkness:bg-[#0f2645] darkness:bg-opacity-60 darkness:ring-[#113c6e]">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 text-slate-800 font-medium text-lg darkness:text-slate-100">
                    @svg('tabler-activity', 'w-5 h-5 text-blue-500 darkness:text-sky-400')
                    @lang('sApi::global.recent_requests')
                </div>
                {{--<a href="{{route('sTask.index')}}" class="text-sm text-blue-600 hover:underline darkness:text-sky-400">
                    @lang('sApi::global.view_all')
                </a>--}}
            </div>
            @if(count($requests ?? []) > 0)
                <div class="py-3 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-slate-200 darkness:border-slate-700">
                        <tr class="text-left text-sm text-slate-600 darkness:text-slate-300">
                            <th class="pb-3 font-medium">@lang('sApi::global.created')</th>
                            <th class="pb-3 font-medium">@lang('sApi::global.method')</th>
                            <th class="pb-3 font-medium">@lang('sApi::global.path')</th>
                            <th class="pb-3 font-medium">@lang('sApi::global.status')</th>
                            <th class="pb-3 font-medium">@lang('sApi::global.duration')</th>
                            <th class="pb-3 font-medium">@lang('sApi::global.level')</th>
                            {{--<th class="pb-3 font-medium text-right">@lang('sApi::global.actions')</th>--}}
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 darkness:divide-slate-700">
                        @foreach($requests as $request)
                            <tr class="text-sm darkness:text-slate-100">
                                <td class="py-3 font-mono text-xs text-slate-500 darkness:text-slate-400">{{$request['created_at']}}</td>
                                <td class="py-3">
                                    @switch($request['method'])
                                        @case('GET')
                                            <span class="px-2 py-1 rounded text-green-600 bg-green-50 text-xs font-medium">
                                                {{$request['method']}}
                                            </span>
                                            @break
                                        @case('POST')
                                            <span class="px-2 py-1 rounded text-amber-600 bg-amber-50 text-xs font-medium">
                                                {{$request['method']}}
                                            </span>
                                            @break
                                        @case('PUT')
                                            <span class="px-2 py-1 rounded text-blue-600 bg-blue-50 text-xs font-medium">
                                                {{$request['method']}}
                                            </span>
                                            @break
                                        @case('PATCH')
                                            <span class="px-2 py-1 rounded text-violet-600 bg-violet-50 text-xs font-medium">
                                                {{$request['method']}}
                                            </span>
                                            @break
                                        @case('DELETE')
                                            <span class="px-2 py-1 rounded text-red-600 bg-red-50 text-xs font-medium">
                                                {{$request['method']}}
                                            </span>
                                            @break
                                        @case('HEAD')
                                            <span class="px-2 py-1 rounded text-teal-600 bg-teal-50 text-xs font-medium">
                                                {{$request['method']}}
                                            </span>
                                            @break
                                        @default
                                            <span class="px-2 py-1 rounded text-pink-600 bg-pink-50 text-xs font-medium">
                                                {{$request['method']}}
                                            </span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="py-3">{{$request['path']}}</td>
                                <td class="py-3">{{$request['status']}}</td>
                                <td class="py-3">{{$request['duration']}} ms</td>
                                <td class="py-3">
                                    @switch($request['level'])
                                        @case('DEBUG')
                                            <span class="px-2 py-1 rounded text-slate-600 bg-slate-100 text-xs font-medium">
                                                {{ucfirst(strtolower($request['level']))}}
                                            </span>
                                            @break
                                        @case('NOTICE')
                                            <span class="px-2 py-1 rounded text-indigo-600 bg-indigo-50 text-xs font-medium">
                                                {{ucfirst(strtolower($request['level']))}}
                                            </span>
                                            @break
                                        @case('WARNING')
                                            <span class="px-2 py-1 rounded text-amber-600 bg-amber-50 text-xs font-medium">
                                                {{ucfirst(strtolower($request['level']))}}
                                            </span>
                                            @break
                                        @case('ERROR')
                                            <span class="px-2 py-1 rounded text-red-600 bg-red-50 text-xs font-medium">
                                                {{$request['level']}}
                                            </span>
                                            @break
                                        @case('CRITICAL')
                                            <span class="px-2 py-1 rounded text-rose-700 bg-rose-50 text-xs font-medium">
                                                {{ucfirst(strtolower($request['level']))}}
                                            </span>
                                            @break
                                        @case('ALERT')
                                            <span class="px-2 py-1 rounded text-fuchsia-700 bg-fuchsia-50 text-xs font-medium">
                                                {{$request['level']}}
                                            </span>
                                            @break
                                        @case('EMERGENCY')
                                            <span class="px-2 py-1 rounded text-violet-700 bg-violet-50 text-xs font-medium">
                                                {{$request['level']}}
                                            </span>
                                            @break
                                        @default
                                            <span class="px-2 py-1 rounded text-blue-600 bg-blue-50 text-xs font-medium">
                                                {{ucfirst(strtolower($request['level']))}}
                                            </span>
                                            @break
                                    @endswitch
                                </td>
                                {{--<td class="py-3 text-right">
                                    <a href="{{route('stask.task.show', $task->id)}}" class="text-blue-600 hover:underline text-xs darkness:text-sky-400">
                                        @lang('sApi::global.details')
                                    </a>
                                </td>--}}
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-slate-600 text-sm darkness:text-slate-100">@lang('sApi::global.no_requests_yet')</p>
            @endif
        </div>
    </section>
@endsection