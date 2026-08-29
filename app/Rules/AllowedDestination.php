<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Guards what an admin may point a catalog entry at.
 *
 * Every destination on this site is reachable through a redirect that hides it
 * until the moment it is tapped, so the check on what goes in belongs at the
 * point of entry rather than in a later review.
 */
class AllowedDestination implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = parse_url((string) $value, PHP_URL_HOST);

        if ($host === null || $host === false) {
            $fail('URL tidak valid.');

            return;
        }

        $host = strtolower($host);

        foreach (config('search.blocked_hosts') as $blocked) {
            $blocked = strtolower(trim($blocked));

            // Match the host itself and anything beneath it, so blocking
            // "contoh.com" also covers "a.contoh.com".
            if ($blocked !== '' && ($host === $blocked || str_ends_with($host, '.'.$blocked))) {
                $fail("Host {$host} masuk daftar blokir.");

                return;
            }
        }

        $allowed = config('search.allowed_hosts');

        if ($allowed === []) {
            return;
        }

        foreach ($allowed as $permitted) {
            $permitted = strtolower(trim($permitted));

            if ($permitted !== '' && ($host === $permitted || str_ends_with($host, '.'.$permitted))) {
                return;
            }
        }

        $fail("Host {$host} tidak ada dalam daftar yang diizinkan.");
    }
}
