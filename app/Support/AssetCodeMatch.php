<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AssetCodeMatch
{
    /**
     * Finds assets whose fixed code is the same numeric value, allowing only
     * extra zeroes at the end (for example 500081145 and 500081145000).
     */
    public static function assetIds(string $code): array
    {
        $code = trim($code);
        if (! preg_match('/^\d{6,}$/', $code)) {
            return [];
        }

        return DB::table('activos')->select(['id', 'activo_fijo'])->orderBy('id')->cursor()
            ->filter(fn (object $asset) => self::equivalent($asset->activo_fijo, $code))
            ->pluck('id')->all();
    }

    public static function equivalent(?string $storedCode, string $searchedCode): bool
    {
        $storedCode = trim((string) $storedCode);
        $searchedCode = trim($searchedCode);

        if ($storedCode === $searchedCode) {
            return true;
        }

        if (! preg_match('/^\d{6,}$/', $storedCode) || ! preg_match('/^\d{6,}$/', $searchedCode)) {
            return false;
        }

        return self::canonicalNumericCode($storedCode) === self::canonicalNumericCode($searchedCode);
    }

    /**
     * Returns the numeric identity used for scans and duplicate prevention.
     * Non-numeric codes deliberately have no alternate representation.
     */
    public static function canonicalNumericCode(?string $code): ?string
    {
        $code = trim((string) $code);

        if (! preg_match('/^\d{6,}$/', $code)) {
            return null;
        }

        return rtrim($code, '0') ?: '0';
    }
}
