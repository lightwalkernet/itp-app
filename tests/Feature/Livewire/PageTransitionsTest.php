<?php

/*
 * The web build fades between pages; the phone keeps its slides (brief 1 §7, platform.md §8).
 * Read from source, like the other transition checks: the browser runs these, not PHP.
 */
it('fades pages on the web build only, leaving the phone’s slides alone', function () {
    $css = file_get_contents(resource_path('css/transitions.css'));
    $js = file_get_contents(resource_path('js/transitions.js'));

    expect($css)
        ->toContain('html:not(.is-native) .app-page { transition: opacity 200ms ease; }')
        ->toContain('html.is-fading:not(.is-native) .app-page { opacity: 0; }')
        ->toContain('html.is-native[data-nav-direction="forward"]::view-transition-old(page-out) { animation-name: app-out-left; }')
        ->and($js)
        ->toContain("document.addEventListener('livewire:navigating', () => {\n    if (canAnimate()) return")
        ->toContain("root.classList.add('is-fading')");
});

it('lets a tab match more than one path, so an ITP’s page lights ITPs and About lights You', function () {
    $js = file_get_contents(resource_path('js/transitions.js'));
    $bar = file_get_contents(resource_path('views/components/bar.blade.php'));

    expect($js)->toContain("tab.dataset.tabMatch.split(' ')")
        ->and($bar)->toContain('data-tab-match="/itps /universal"')
        ->toContain("'/you /about /delete-account'");
});
