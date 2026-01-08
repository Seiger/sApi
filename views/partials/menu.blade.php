<aside :class="open ? 'w-60' : 'w-16'" class="s-nav">
    <div class="s-nav-header">
        <a href="{{route('sApi.dashboard')}}" class="flex items-center gap-1 text-xl font-bold" x-show="open" x-cloak>sApi</a>
        <img x-show="!open" x-cloak src="{{asset('site/sapi.svg')}}" class="w-8 h-8 pointer-events-none filter drop-shadow-[0_0_6px_#3b82f6]" alt="sApi">
    </div>
    <nav class="s-nav-menu">
        <a href="{{route('sApi.dashboard')}}" @class(['s-nav-menu-item', 's-nav-menu-item--active' => 'sApi.dashboard' == $activeRoute])>
            @svg('tabler-layout-dashboard', 'w-6 h-6')
            <span x-show="open">@lang('sApi::global.dashboard')</span>
        </a>
        <a href="{{route('sApi.logs')}}" @class(['s-nav-menu-item', 's-nav-menu-item--active' => 'sApi.logs' == $activeRoute])>
            @svg('tabler-activity-heartbeat', 'w-6 h-6')
            <span x-show="open">@lang('sApi::global.logs/timeline')</span>
        </a>
        <a href="{{route('sApi.routes')}}" @class(['s-nav-menu-item', 's-nav-menu-item--active' => 'sApi.routes' == $activeRoute])>
            @svg('tabler-route', 'w-6 h-6')
            <span x-show="open">@lang('sApi::global.routes')</span>
        </a>
    </nav>
    <span @click="toggle()" role="button" tabindex="0" class="s-pin-btn" :class="open ? 'left-24' : 'left-4'" title="Toggle sidebar">
        <template x-if="open">
            @svg('tabler-pinned', 'w-4 h-4 pointer-events-none')
        </template>
        <template x-if="!open">
            @svg('tabler-pin', 'w-4 h-4 pointer-events-none')
        </template>
    </span>
</aside>
