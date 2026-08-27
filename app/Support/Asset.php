<?php

namespace App\Support;

/**
 * A versioned URL for a file in public/.
 *
 * This project has no build step — CSS and JS are plain files served straight
 * by nginx, which means their URLs never change and a browser is entitled to
 * keep the copy it already has for as long as it likes. A deploy therefore
 * looked applied on the server and stale in the visitor's browser, and the two
 * halves of a rename drifted apart: markup asking for one class name while the
 * cached stylesheet still defined the other.
 *
 * Appending the file's modification time gives each release its own URL, so a
 * changed file is fetched and an unchanged one stays cached.
 *
 * `filemtime` is one stat per file, memoised for the request. It is deliberately
 * NOT cached in config: a cached fingerprint that outlives the file it
 * describes is the very problem this exists to prevent.
 */
final class Asset
{
    /** @var array<string, string> */
    private static array $resolved = [];

    private function __construct() {}

    public static function url(string $path): string
    {
        if (isset(self::$resolved[$path])) {
            return self::$resolved[$path];
        }

        $full = public_path($path);
        $stamp = is_file($full) ? filemtime($full) : false;

        // A missing file is a broken page either way; adding a fingerprint of
        // "false" would only make it harder to see why.
        return self::$resolved[$path] = $stamp === false
            ? asset($path)
            : asset($path).'?v='.$stamp;
    }

    /** Test seam: forget what has been resolved this process. */
    public static function flush(): void
    {
        self::$resolved = [];
    }
}
