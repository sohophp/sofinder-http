<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http;
use SohoPHP\SoFinder\Exception\SoFinderException;

/** Bounds form/JSON metadata; uploaded streams have their own upload budgets. */
final class RequestInput
{
    public const MAX_BYTES = 1_048_576;
    public const MAX_DEPTH = 32;
    public const MAX_VALUES = 4096;

    /** @param array<mixed> $values */
    public static function validate(array $values): void
    {
        $count = 0; $bytes = 0;
        self::walk($values, 0, $count, $bytes);
    }

    /** @return array<string,mixed> */
    public static function json(string $body): array
    {
        if (strlen($body) > self::MAX_BYTES) self::tooLarge();
        try { $values = json_decode($body, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new SoFinderException('The JSON request body is invalid or too deeply nested.', 'invalid_json', 400); }
        if (!is_array($values) || !str_starts_with(ltrim($body), '{')) throw new SoFinderException('The JSON request body must be an object.', 'invalid_json', 400);
        self::validate($values);
        return $values;
    }

    /** @param array<mixed> $values */
    private static function walk(array $values, int $depth, int &$count, int &$bytes): void
    {
        if ($depth >= self::MAX_DEPTH) self::tooLarge();
        foreach ($values as $key => $value) {
            $count++; $bytes += strlen((string) $key) + 16;
            if (is_string($value)) $bytes += strlen($value);
            elseif (is_array($value)) self::walk($value, $depth + 1, $count, $bytes);
            elseif (!is_scalar($value) && $value !== null) throw new SoFinderException('The request input contains an unsupported value.', 'invalid_input', 400);
            if ($count > self::MAX_VALUES || $bytes > self::MAX_BYTES) self::tooLarge();
        }
    }

    public static function tooLarge(): never { throw new SoFinderException('The request metadata exceeds its byte, field or depth budget.', 'request_too_large', 413); }
}
