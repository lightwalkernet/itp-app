<?php

namespace App\Support;

/**
 * The room a page declares on its root element (`data-room`, `data-bar`, `data-scene`; see
 * resources/js/rooms.js), read from the rendered page so the layout can put it on <html> in the
 * response itself. Otherwise the first frame is drawn as a light room and only then turns white.
 */
final class PageRoom
{
    /**
     * The attributes for <html>, as rooms.js would set them: the first element that declares a
     * room, `light` when none does.
     *
     * @return array{data-room: 'light'|'white', data-bar?: 'hidden', data-scene?: string}
     */
    public static function attributes(string $page): array
    {
        if (! preg_match('/<[a-z][^>]*\sdata-room="([^"]*)"[^>]*>/i', $page, $match)) {
            return ['data-room' => 'light'];
        }

        $tag = $match[0];
        $attributes = ['data-room' => $match[1] === 'white' ? 'white' : 'light'];

        if (preg_match('/\sdata-bar="hidden"/', $tag)) {
            $attributes['data-bar'] = 'hidden';
        }

        if (preg_match('/\sdata-scene="([^"]+)"/', $tag, $scene)) {
            $attributes['data-scene'] = $scene[1];
        }

        return $attributes;
    }
}
