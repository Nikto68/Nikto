<?php

declare(strict_types=1);

use App\Exchange\Binance;
use App\Exchange\MEXC;
use App\Exchange\Wallex;
use App\Support\Env;

// Adding a new exchange later means: write the adapter class implementing
// ExchangeInterface, add one entry here, add its ENV vars. Nothing else in
// the codebase (scanner, strategy, signal engine) needs to change — see
// src/Exchange/ExchangeManager.php.
return [
    'binance' => [
        'enabled' => Env::bool('BINANCE_ENABLED', true),
        'adapter' => Binance::class,
        'api_key' => Env::string('BINANCE_API_KEY', ''),
        'api_secret' => Env::string('BINANCE_API_SECRET', ''),
        'rest_base' => rtrim(Env::string('BINANCE_REST_BASE', 'https://api.binance.com') ?? '', '/'),
        'ws_base' => rtrim(Env::string('BINANCE_WS_BASE', 'wss://stream.binance.com:9443') ?? '', '/'),
        // Binance weight-based limit: 6000 weight / minute on most endpoints.
        'rate_limit' => [
            'max_requests' => 1100,
            'per_seconds' => 60,
        ],
    ],

    'mexc' => [
        'enabled' => Env::bool('MEXC_ENABLED', true),
        'adapter' => MEXC::class,
        'api_key' => Env::string('MEXC_API_KEY', ''),
        'api_secret' => Env::string('MEXC_API_SECRET', ''),
        'rest_base' => rtrim(Env::string('MEXC_REST_BASE', 'https://api.mexc.com') ?? '', '/'),
        'ws_base' => rtrim(Env::string('MEXC_WS_BASE', 'wss://wbs.mexc.com/ws') ?? '', '/'),
        'rate_limit' => [
            'max_requests' => 500,
            'per_seconds' => 60,
        ],
    ],

    'wallex' => [
        'enabled' => Env::bool('WALLEX_ENABLED', true),
        'adapter' => Wallex::class,
        'api_key' => Env::string('WALLEX_API_KEY', ''),
        'api_secret' => Env::string('WALLEX_API_SECRET', ''),
        'rest_base' => rtrim(Env::string('WALLEX_REST_BASE', 'https://api.wallex.ir') ?? '', '/'),
        'ws_base' => rtrim(Env::string('WALLEX_WS_BASE', 'wss://api.wallex.ir/socket.io') ?? '', '/'),
        'rate_limit' => [
            'max_requests' => 200,
            'per_seconds' => 60,
        ],
    ],
];
