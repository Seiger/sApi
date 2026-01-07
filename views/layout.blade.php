<!DOCTYPE html>
<html lang="{{ManagerTheme::getLang()}}" dir="{{ManagerTheme::getTextDir()}}">
<head>
    <title>{{$pageTitle}} — sApi - Evolution CMS</title>
    <base href="{{EVO_MANAGER_URL}}">
    <meta http-equiv="Content-Type" content="text/html; charset={{ManagerTheme::getCharset()}}"/>
    <meta name="viewport" content="initial-scale=1.0,user-scalable=no,maximum-scale=1,width=device-width"/>
    <meta name="theme-color" content="#0b1a2f"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <link rel="icon" type="image/svg+xml" href="{{\Seiger\sApi\sApi::asset('sapi.svg')}}" />
    <style>[x-cloak]{display:none!important}</style>
    <link rel="stylesheet" href="{{\Seiger\sApi\sApi::asset('sapi.min.css')}}?{{evo()->getConfig('sApiVer','')}}">
    {!!ManagerTheme::getMainFrameHeaderHTMLBlock()!!}
    <script defer src="https://unpkg.com/alpinejs@latest"></script>
    <script defer src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="{{ManagerTheme::getTextDir()}} {{ManagerTheme::getThemeStyle()}}" data-evocp="color">
<div class="min-h-screen bg-slate-50">
    <div class="flex">
        @include('sApi::partials.sidebar')
        <main class="flex-1 min-h-screen">
            <header class="sticky top-0 z-10 border-b border-slate-200 bg-white">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <h1 class="text-lg font-semibold text-slate-900">{{$pageTitle}}</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        @yield('primary')
                        <button type="button" class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                            Primary action
                        </button>
                    </div>
                </div>
            </header>

            <div class="px-6 py-6">
                @yield('content')
            </div>
        </main>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
@include('manager::partials.debug')
</body>
</html>

