<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

/** Builds RFC 6266 dispositions without depending on a framework header helper. */
final class ContentDisposition
{
    public static function make(string $disposition, string $fileName): string
    {
        $fallback = self::fallback($fileName);
        $header = strtolower($disposition) === 'inline' ? 'inline' : 'attachment';
        $header .= '; filename=' . self::quoted($fallback);
        if ($fallback !== $fileName) {
            $header .= "; filename*=utf-8''" . rawurlencode($fileName);
        }

        return $header;
    }

    private static function quoted(string $value): string
    {
        return preg_match('/^[A-Za-z0-9!#$&+.^_`|~-]+$/D', $value) === 1
            ? $value
            : '"' . addcslashes($value, "\\\"") . '"';
    }

    private static function fallback(string $fileName): string
    {
        if (preg_match('/^[\x20-\x7E]+$/D', $fileName) === 1 && !str_contains($fileName, '%') && !str_contains($fileName, '/') && !str_contains($fileName, '\\')) {
            return $fileName;
        }
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]{1,16}$/D', $extension) === 1 ? '.' . $extension : '';
        $stem = (string) pathinfo($fileName, PATHINFO_FILENAME);
        $stem = preg_replace('/[^\x20-\x7E]+/u', '-', $stem) ?? '';
        $stem = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $stem) ?? '';
        $stem = trim(preg_replace('/[ ._-]+/', '-', $stem) ?? '', '-');
        if ($stem === '') {
            $stem = 'download';
        }

        return substr($stem, 0, 96) . $extension;
    }
}
