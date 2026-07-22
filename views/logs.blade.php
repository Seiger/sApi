@extends('sApi::layout')

@section('content')
    <section class="p-6">
        <div class="rounded-2xl bg-white/70 ring-1 ring-blue-200 p-6 darkness:bg-[#0f2645] darkness:bg-opacity-60 darkness:ring-[#113c6e]">
            <div class="flex items-center gap-2 mb-4 text-slate-800 font-medium text-lg darkness:text-slate-100">
                @svg('tabler-activity-heartbeat', 'w-5 h-5 text-blue-500 darkness:text-sky-400')
                @lang('sApi::global.logs/timeline')
            </div>

            <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 darkness:bg-[#132a44] darkness:text-slate-200">
                @svg('tabler-info-circle', 'w-5 h-5 text-blue-500 darkness:text-sky-400')
                <span>{{$message ?? 'Not implemented.'}}</span>
            </div>
        </div>
    </section>
@endsection
