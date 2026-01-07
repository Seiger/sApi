@extends('sApi::layout')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="text-lg font-semibold text-slate-900">{{$pageTitle}}</div>
        <div class="mt-2 text-sm text-slate-600">{{$message ?? 'Not implemented.'}}</div>
    </div>
@endsection

