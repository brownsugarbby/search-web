<?php

namespace App\Support;

use Illuminate\Http\Request;

class Visitor
{
    /**
     * A stable per-visitor token that is not an IP address.
     *
     * Salted with APP_KEY and hashed, so the logs support "how many distinct
     * people searched this" without the database ever holding an address that
     * identifies someone.
     */
    public static function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip === null ? null : hash('sha256', config('app.key').'|'.$ip);
    }
}
