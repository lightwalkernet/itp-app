<?php

use Symfony\Component\Finder\Finder;

/*
 * platform.md §3: itp-app ships on every phone. Nothing in it may reach generation, AI or voice
 * code, server-only code, Horizon or admin code. `toUse()` only matches classes that exist, and
 * none of these is installed here, so this reads every name in the shipped code instead.
 */
it('names no server-only namespace in shipped code', function () {
    $serverOnly = ['Laravel\Ai', 'Prism', 'ElevenLabs', 'Itp\Server', 'Laravel\Horizon'];
    $root = dirname(__DIR__, 2);

    $files = Finder::create()->files()->name('*.php')
        ->in(array_map(fn (string $directory) => "{$root}/{$directory}", ['app', 'bootstrap', 'config', 'database', 'resources/views', 'routes']))
        ->exclude('cache');

    $references = [];

    foreach ($files as $file) {
        foreach (PhpToken::tokenize($file->getContents()) as $token) {
            if (! $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                continue;
            }

            $name = ltrim($token->text, '\\');

            foreach ($serverOnly as $namespace) {
                if (str_starts_with($name, $namespace.'\\')) {
                    $references[] = $file->getRelativePathname().': '.$name;
                }
            }
        }
    }

    expect($references)->toBe([]);
});
