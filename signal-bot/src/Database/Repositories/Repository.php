<?php

declare(strict_types=1);

namespace App\Database\Repositories;

use App\Database\Database;

/**
 * Every repository just wraps one or a few related tables behind a typed
 * PHP API. Nothing outside src/Database/Repositories talks SQL directly —
 * strategy/signal/bot code call these methods, which keeps prepared
 * statements and table names in one auditable place per table.
 */
abstract class Repository
{
    public function __construct(
        protected readonly Database $db,
    ) {
    }
}
