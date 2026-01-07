<aside class="w-64 shrink-0 border-r border-slate-200 bg-slate-900 text-slate-100">
    <div class="flex items-center gap-3 px-4 py-4 border-b border-slate-800">
        <img src="{{\Seiger\sApi\sApi::asset('sapi.svg')}}" alt="sApi" class="h-9 w-9">
        <div>
            <div class="text-sm font-semibold leading-5">sApi</div>
            <div class="text-xs text-slate-400">Manager</div>
        </div>
    </div>

    @php($current = $activeRouteName ?? '')
    <nav class="px-2 py-3 space-y-1">
        @php($items = [
            ['name' => 'Dashboard', 'route' => 'sApi.dashboard', 'icon' => 'layout-dashboard'],
            ['name' => 'Routes', 'route' => 'sApi.routes', 'icon' => 'route'],
            ['name' => 'Auth', 'route' => 'sApi.auth', 'icon' => 'key-round'],
            ['name' => 'Providers', 'route' => 'sApi.providers', 'icon' => 'package'],
            ['name' => 'Logs', 'route' => 'sApi.logs', 'icon' => 'file-text'],
        ])
        @foreach($items as $item)
            @php($active = $current === $item['route'])
            <a href="{{route($item['route'])}}"
               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $active ? 'bg-slate-800 text-white' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }}">
                <i data-lucide="{{$item['icon']}}" class="h-4 w-4"></i>
                <span>{{$item['name']}}</span>
            </a>
        @endforeach
    </nav>
</aside>

