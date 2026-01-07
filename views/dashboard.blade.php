@extends('sApi::layout')

@section('content')
    <div class="flex items-center gap-4">
        <img src="{{\Seiger\sApi\sApi::asset('sapi.svg')}}" alt="sApi" class="h-12 w-12">
        <div>
            <div class="text-2xl font-semibold text-slate-900">sApi</div>
            <div class="text-sm text-slate-600">API core for Evolution CMS</div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs font-medium text-slate-500">Status</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">UI skeleton</div>
            <div class="mt-1 text-sm text-slate-600">No business logic yet</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs font-medium text-slate-500">Routes</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">Config-driven</div>
            <div class="mt-1 text-sm text-slate-600">See Routes page</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs font-medium text-slate-500">Auth</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">JWT</div>
            <div class="mt-1 text-sm text-slate-600">Token issuance available</div>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-slate-900">Quick Links</div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{route('sApi.routes')}}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 hover:bg-slate-50">
                View Routes
            </a>
            <a href="{{route('sApi.providers')}}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 hover:bg-slate-50">
                Providers
            </a>
            <a href="{{route('sApi.logs')}}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 hover:bg-slate-50">
                Logs
            </a>
        </div>
    </div>
@endsection

