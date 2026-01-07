<?php

/**
 * sApi Plugin
 *
 * Add sApi menu item to Evolution CMS manager
 *
 * @package Seiger\sApi
 * @author Seiger IT Team
 * @since 1.0.0
 */
Event::listen('evolution.OnManagerMenuPrerender', function($params) {
    if (evo()->hasPermission('sapi')) {
        $menu['sapi'] = [
            'sapi',
            'tools',
            '<img src="' . asset('site/sapi.svg') . '" width="20" height="20" style="display:inline-block;vertical-align:middle;margin-right:8px;transition:filter 0.2s ease;" class="sapi-logo">' .  __('sApi::global.title'),
            route('sApi.dashboard'),
            __('sApi::global.title'),
            "",
            "",
            "main",
            0,
            50,
        ];

        return serialize(array_merge($params['menu'], $menu));
    }
});
