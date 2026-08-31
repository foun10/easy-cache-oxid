<?php

declare(strict_types=1);

namespace foun10\EasyCache\Core;

/**
 * Translates the whitelist between the array the module stores and the single
 * line an admin edits on the settings page.
 *
 * Kept apart from EasyCache itself so it can be tested without a shop: the
 * parsing rules are where a typo turns into "nothing is cached any more", and
 * an admin will not read a stack trace to find out that a trailing comma cost
 * them their start page.
 */
class ControllerWhitelist
{
    /**
     * Accepts commas, whitespace and line breaks as separators, so pasting
     * either "start, alist" or one entry per line does the same thing.
     *
     * @return string[] trimmed, de-duplicated, order preserved
     */
    public static function parse(string $raw): array
    {
        $parts = preg_split('/[\s,]+/', $raw) ?: [];

        $list = [];
        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '' && !in_array($part, $list, true)) {
                $list[] = $part;
            }
        }

        return $list;
    }

    /**
     * @param string[] $list
     */
    public static function format(array $list): string
    {
        return implode(', ', $list);
    }
}
