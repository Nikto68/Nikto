<?php

/**
 * Copy this file to `env.php` on the server and fill in the real values.
 * `env.php` is git-ignored on purpose — a bot token in the repository is a
 * compromised bot token.
 *
 * Values set from the Telegram admin panel are stored in the database and
 * take precedence over the ones here, so anything marked "panel-editable"
 * can be retuned live without touching this file.
 */
return [
    // ---------------------------------------------------------------------
    // Telegram
    // ---------------------------------------------------------------------
    'TELEGRAM_BOT_TOKEN' => 'PUT-YOUR-BOT-TOKEN-HERE',
    // Leave empty to disable the check. If you set one here you must also
    // register the same value with setWebhook, or every update is rejected.
    'TELEGRAM_WEBHOOK_SECRET' => '',
    // Numeric Telegram user IDs allowed into the admin panel, comma separated.
    'ADMIN_IDS' => '',

    // ---------------------------------------------------------------------
    // Exchanges — public market data needs no API key on any of these.
    // Each exchange is isolated by a circuit breaker: one being blocked or
    // down just means it contributes 0 symbols.
    // ---------------------------------------------------------------------
    'ENABLED_EXCHANGES' => 'mexc,binance,bybit,okx,kucoin,gate,bitget,htx,cryptocompare',

    // The venue a signal is issued on when several list the same pair. MEXC
    // leads because it lists far more small caps and memecoins than the
    // others; a setup on a coin the reader cannot actually trade is wasted.
    'PRIMARY_EXCHANGE' => 'mexc',

    // Where YOU actually place the trades. Only coins listed on at least
    // one of these are signalled, so a setup never lands on a coin your
    // exchange does not carry. Set to 'off' to disable the filter.
    //
    // It fails OPEN on purpose: if a listing endpoint moves or is blocked,
    // the filter switches itself off rather than silencing the bot. Check
    // 📊 Scanner -> 🏦 صرافی‌های معاملاتی to see whether it actually loaded,
    // and override a URL below if one has changed.
    'TRADABLE_VENUES' => 'toobit,ourbit',
    'TOOBIT_LISTINGS_URL' => '',
    'OURBIT_LISTINGS_URL' => '',
    'VENUE_LISTINGS_TTL' => '21600',

    'BINANCE_API_KEY' => '',
    'BINANCE_API_SECRET' => '',
    'BINANCE_REST_BASE' => 'https://api.binance.com',
    'BINANCE_WS_BASE' => 'wss://stream.binance.com:9443',

    'MEXC_API_KEY' => '',
    'MEXC_API_SECRET' => '',
    'MEXC_REST_BASE' => 'https://api.mexc.com',
    'MEXC_WS_BASE' => 'wss://wbs.mexc.com',

    'BYBIT_REST_BASE' => 'https://api.bybit.com',
    'OKX_REST_BASE' => 'https://www.okx.com',
    'KUCOIN_REST_BASE' => 'https://api.kucoin.com',
    'GATE_REST_BASE' => 'https://api.gateio.ws',
    'BITGET_REST_BASE' => 'https://api.bitget.com',
    'HTX_REST_BASE' => 'https://api.huobi.pro',

    'CRYPTOCOMPARE_API_KEY' => '',
    'CRYPTOCOMPARE_REST_BASE' => 'https://min-api.cryptocompare.com',

    // Coinalyze — free-tier liquidation/OI data (coinalyze.net/account for a
    // free API key). Panel-editable ("📰 اخبار و لیکویید"). Symbol is in
    // Coinalyze's own notation, e.g. BTCUSDT_PERP.A for Binance USDT-M.
    'COINALYZE_API_KEY' => '',
    'COINALYZE_REST_BASE' => 'https://api.coinalyze.net/v1',
    'COINALYZE_SYMBOL' => 'BTCUSDT_PERP.A',

    // ---------------------------------------------------------------------
    // Scanner — which symbols make it into the tradable universe.
    // The defaults are deliberately wide: this bot is meant to trade
    // altcoins and low-caps, not just majors. All panel-editable.
    // ---------------------------------------------------------------------
    // 0 = no cap. Every perpetual above MIN_VOLUME_USDT stays in the
    // universe; the rotation below bounds the work by time instead, so the
    // whole market gets covered rather than the same slice of it.
    'SCANNER_TOP_N' => '0',
    'SCANNER_INTERVAL_SECONDS' => '900',
    // The only real gate on which coins exist for this bot. Set at the ALT
    // tier floor: a coin thinner than this is exactly the kind a small
    // entry can get pumped-and-dumped or slipped on, real 24h number or
    // not. Lower it from the panel only if you deliberately want to trade
    // thin/micro coins again.
    'MIN_VOLUME_USDT' => '5000000',
    // A real coin above the volume floor above spreads 0.02-0.15% on a
    // decent exchange. Anything much wider than that is either a thin book
    // or a market moving too violently to enter safely -- exactly the
    // moment a signal should NOT go out. 10% let essentially everything
    // through and did nothing; at high leverage with stops sometimes as
    // tight as 1.2-1.5%, even a "modest" spread eats a real chunk of that
    // budget before price has moved at all.
    'MAX_SPREAD_PERCENT' => '0.3',
    // Quote assets a pair may be priced in. Leave empty for the dollar
    // stablecoin set (USDT, USDC, FDUSD, BUSD, TUSD, DAI) — do NOT expect an
    // empty value to mean "everything". Regional exchanges list fiat pairs,
    // and without this filter the scanner ranks things like USDTTMN (Tether
    // priced in Iranian toman) as tradable. Use '*' if you really want no
    // filter at all.
    'ALLOWED_QUOTE_ASSETS' => 'USDT',

    // Whether stablecoin-vs-stablecoin pairs (USDCUSDT and friends) count as
    // tradable. They are pegs; leveraged signals on them are meaningless.
    'INCLUDE_STABLECOIN_PAIRS' => 'false',

    // Timeframes to COLLECT candles for. Must be a superset of
    // SIGNAL_TIMEFRAMES below, or those timeframes have no data to work with.
    'TIMEFRAMES' => '15m,30m,1h,2h',

    // ---------------------------------------------------------------------
    // Automatic mode — the behaviour described in 🤖 اتومات in the panel.
    // ---------------------------------------------------------------------

    // How many of a pass's qualifying candidates may go out at once. 1
    // means exactly one signal per pass, never a burst of several.
    'SIGNALS_PER_PASS' => '1',

    // Timeframes a signal may be issued on.
    'SIGNAL_TIMEFRAMES' => '15m,30m,1h,2h',

    // Four scale-out targets and the stop, as LEVERAGED account
    // percentages, not price moves and not risk multiples: "first target
    // pays 28% of margin, second 50%, third 90%, fourth (final) 160%, and
    // the stop never costs more than 30%". At 20x those are price moves of
    // 1.4% / 2.5% / 4.5% / 8% / 1.5%. TP1 sitting below the stop's leveraged
    // percentage is intentional -- a fast, close first scoop taken before
    // the trade is proven; the real reward comes from TP2-TP4 if price
    // keeps going. The stop trails up behind each target as it is hit
    // (entry after TP1, TP1's price after TP2, TP2's price after TP3), so
    // the worst case keeps improving as the trade progresses.
    'TP1_LEVERAGED_PCT' => '28',
    'TP2_LEVERAGED_PCT' => '50',
    'TP3_LEVERAGED_PCT' => '90',
    'TP4_LEVERAGED_PCT' => '160',
    'MAX_STOP_LEVERAGED_PCT' => '30',

    // On TP1: move the stop to entry and announce the trade as risk free.
    'RISK_FREE_ENABLED' => 'true',

    // How much of the position (%) a trader following the signal is
    // expected to close at TP1. Used only for the PnL% shown when a trade
    // later closes at breakeven or a later target -- that close is a blend
    // of the TP1 fill and the final one, not just the final price alone.
    'TP1_CLOSE_PERCENT' => '50',

    // Trade-management advisories, each sent at most once per trade as a
    // reply -- never a forced close. Before TP1: if price has given back
    // this % of the ORIGINAL stop distance without ever reaching TP1, a
    // heads-up that the trade is going wrong. After TP3: if price retraces
    // this % of the TP2-TP3 leg from its post-TP3 peak without reaching
    // TP4, a "consider locking in profit here" reply instead of only
    // waiting for the trailing stop.
    'ADVISORY_STOP_WARN_PCT' => '70',
    'ADVISORY_STALL_RETRACE_PCT' => '50',

    // 0 = no cap, which is the point: the scan is a ROTATION. Each cron
    // invocation resumes where the last one stopped, visits as far as
    // ROTATION_BUDGET_SECONDS allows, and saves its position — so several
    // hundred coins are covered on a few-minute cycle instead of the first
    // sixty being re-checked every minute and the rest never at all.
    //
    // Roughly a coin per second on a typical shared host: ~60 per
    // invocation, so ~600 perps sweep in about ten minutes.
    'SIGNAL_MAX_SYMBOLS_PER_PASS' => '0',
    'ROTATION_BUDGET_SECONDS' => '32',
    'ROTATION_MAX_SYMBOLS' => '400',

    'MIN_SIGNAL_SCORE' => '45',
    'SIGNAL_COOLDOWN_SECONDS' => '900',
    'MIN_RISK_REWARD' => '1.2',

    // After a coin stops out, no fresh signal on it (even a genuinely new,
    // independently-qualifying setup) for this many seconds -- a stop is
    // the market rejecting a level, and walking straight back into the
    // same area minutes later is still fighting that same rejection. 0
    // disables this. Checked per base asset, so a BTC stop on one exchange
    // also cools BTC down on every other exchange the scanner covers.
    'SYMBOL_LOSS_COOLDOWN_SECONDS' => '10800',

    // ---------------------------------------------------------------------
    // Leverage
    // ---------------------------------------------------------------------
    // These coins get the high-leverage tier.
    'LEVERAGE_MAJOR_ASSETS' => 'BTC,ETH',
    'LEVERAGE_MAJOR' => '20',

    // Everything else lands inside this band automatically, by liquidity and
    // volatility: deep + calm books earn the top, thin or wild ones the
    // bottom. Low-cap/meme pairs are additionally capped below the top.
    'LEVERAGE_ALT_MIN' => '20',
    'LEVERAGE_ALT_MAX' => '25',

    // Fraction of the liquidation distance the stop is allowed to use — a
    // second ceiling on top of MAX_STOP_LEVERAGED_PCT, whichever is tighter.
    // At 20x liquidation is ~5% away, so 0.75 allows 3.75%; the 30% risk
    // budget caps it at 1.5% first, which is the intended binding limit.
    'LEVERAGE_LIQUIDATION_BUFFER' => '0.75',

    // Floor on stop distance as a percent of entry, so a stop can never land
    // inside the spread.
    'MIN_STOP_PERCENT' => '0.12',

    // ---------------------------------------------------------------------
    // Strategy — the quality gate. Every knob here trades signal COUNT for
    // win rate; loosening them produces more signals and worse ones.
    // ---------------------------------------------------------------------
    // confluence_pro combines all six ported indicators and only takes a
    // trade several of them agree on. structure_break is the simpler
    // CHoCH/breakout strategy; default_structure is the original.
    'STRATEGY' => 'confluence_pro',

    // ---------------------------------------------------------------------
    // Confluence engine — how much each module's agreement is worth, and the
    // total a setup must reach. Raising MIN_CONFLUENCE_SCORE means fewer,
    // better-supported signals; lowering it means more, thinner ones.
    //
    // 55 was calibrated back when there were ~10 vote sources, then raised
    // to 100 once there were 18 -- several of which agree with each other by
    // construction during any real trend, so a flat sum of all of them let
    // correlated votes quietly inflate a setup's score.
    //
    // The score is no longer a flat sum. Every vote now belongs to one of
    // six independent evidence groups (structure / liquidity / location /
    // momentum / volume / htf -- see the CONFLUENCE_*_CAP keys below), each
    // capped on its own, and the published score is the sum of those capped
    // totals. Several structure-ish votes firing together (e.g. a BOS, a
    // wave shift and an iBOS on the same bar) now count once toward the
    // structure cap instead of three times toward the total, so the same
    // numeric bar means something stricter than it used to. 85 is the value
    // that reproduces roughly the old bar's selectivity on this new scale
    // AND with the fresh-reversal-zone and room-to-target hard gates added
    // (both prune setups before the score is even computed) -- measured the
    // same way 100 was originally: a synthetic-run checkup
    // (test_group_score_calibration.php in the project's own test scripts)
    // comparing how many setups clear each candidate threshold against the
    // ~40% benchmark every prior calibration has targeted. Recalibrate
    // again the same way if the vote list, the group caps, or a hard gate
    // upstream of the score check changes.
    // ---------------------------------------------------------------------
    'MIN_CONFLUENCE_SCORE' => '75',
    'CONFLUENCE_SETUP_WEIGHT' => '18',      // each of the six entry setups
    'CONFLUENCE_RANGE_WEIGHT' => '16',      // qualified range breakout
    'CONFLUENCE_STRUCTURE_WEIGHT' => '14',  // BOS / CHoCH
    'CONFLUENCE_ZONE_WEIGHT' => '14',       // order block / FVG / supply-demand
    'CONFLUENCE_TREND_WEIGHT' => '12',      // ALMA wave + EMA band agree
    'CONFLUENCE_LIQUIDITY_WEIGHT' => '12',  // sweep, or untapped liquidity ahead
    'CONFLUENCE_HTF_WEIGHT' => '14',        // the next timeframe up agrees
    'CONFLUENCE_PD_WEIGHT' => '12',         // entering from the correct half of the range
    'CONFLUENCE_OTE_WEIGHT' => '10',        // price inside the 62-79% pocket
    'CONFLUENCE_INTERNAL_WEIGHT' => '8',    // micro-structure break (iBOS)

    // Independent scoring groups — each group's contribution to the
    // published score is capped at this many points before the caps are
    // summed, so several correlated votes in one group can't outweigh
    // genuinely independent evidence from another. Which side wins is still
    // decided from the raw vote total, not this capped one.
    'CONFLUENCE_STRUCTURE_CAP' => '30',  // BOS/CHoCH, wave shift, iBOS, range breakout, break-retest/range-break setups
    'CONFLUENCE_LIQUIDITY_CAP' => '28',  // sweeps, fresh/flipped breaker blocks, untapped liquidity ahead, sweep/big-move setups
    'CONFLUENCE_LOCATION_CAP' => '26',   // strong zone, order-block/FVG guard, FVG mitigation, premium/discount, OTE, VWAP/EMA setups
    'CONFLUENCE_MOMENTUM_CAP' => '22',   // squeeze breakout, RSI-KDE reversal, SuperTrend AI, divergence/channel setups
    'CONFLUENCE_VOLUME_CAP' => '14',     // volume imbalance, displacement
    'CONFLUENCE_HTF_CAP' => '22',        // trend alignment (wave+EMA), higher-timeframe bias

    // Two hard filters rather than scores. Both are skipped for sweep
    // reversals, and premium/discount applies only to PULLBACK entries — a
    // breakout sits at the edge of its range by definition, so applying it
    // to both families would refuse every continuation trade.
    'REQUIRE_HTF_ALIGNMENT' => 'true',
    'REQUIRE_DISCOUNT_PREMIUM' => 'true',

    // Which higher timeframe confirms each entry timeframe's HTF bias --
    // roughly a 4x step (15m->1h, 30m->2h, 1h->4h) rather than simply
    // "whatever comes next in SIGNAL_TIMEFRAMES", which for the default
    // 15m/30m/1h/2h list would check 15m against 30m: barely higher at
    // all. A timeframe with no entry here falls back to that old
    // next-in-list behaviour. See Config::htfConfirmTimeframe().
    'HTF_CONFIRM_MAP' => '15m:1h,30m:2h,1h:4h,2h:4h,4h:1d',

    // Sweep-then-big-move trigger: the sweep must leave a real rejection
    // wick, and the confirmation candle must do the work itself.
    'BIG_MOVE_LOOKBACK' => '20',
    'BIG_MOVE_CONFIRM_BARS' => '3',
    'BIG_MOVE_MIN_WICK' => '0.15',
    'BIG_MOVE_BODY_STRENGTH' => '0.55',

    // How far a protective zone may sit from price and still count, and the
    // breathing room added beyond it when the stop is placed.
    'ZONE_REACH_ATR' => '2.5',
    'STOP_PAD_ATR' => '0.25',

    // The stop's padding scales with how choppy this coin has been lately
    // relative to ITS OWN recent past (not a fixed multiple for every
    // market): below VOLATILITY_CALM_RATIO the market is quieter than
    // usual and gets the smaller pad, above VOLATILITY_HOT_RATIO it is
    // choppier and gets the larger one, in between it uses STOP_PAD_ATR.
    'STOP_PAD_ATR_CALM' => '0.15',
    'STOP_PAD_ATR_VOLATILE' => '0.35',
    'VOLATILITY_CALM_RATIO' => '0.85',
    'VOLATILITY_HOT_RATIO' => '1.3',

    // A hard reject: the nearest untapped liquidity / EQH / EQL ahead of
    // the trade (the same level the planner later caps tp4 against) must be
    // at least this many multiples of the stop's own risk distance away.
    // A target 0.3R ahead is not worth publishing even if everything else
    // about the setup is clean. Only checked when such a level exists at
    // all -- no opposing structure in reach means nothing to refuse over.
    'MIN_ROOM_TO_TARGET_R' => '1.5',

    // How many candles old a BOS / CHoCH may be and still be tradable.
    'STRUCTURE_MAX_AGE' => '3',

    // Setup scanner tuning (the six triggers).
    'SETUP_VOLUME_MULT' => '1.0',      // volume vs its 20-candle average
    'VWAP_AWAY_BARS' => '6',           // bars on one side before a reclaim counts
    'EMA_TOUCH_WINDOW' => '3',         // recency of the 21 EMA touch
    'SETUP_PIVOT_LENGTH' => '5',
    'RETEST_TOLERANCE_ATR' => '0.3',
    'RETEST_WINDOW' => '20',
    'SWEEP_LOOKBACK' => '20',
    'DIVERGENCE_GAP' => '60',

    // Range detector. A band under RANGE_ABS_COMPRESSION x ATR*sqrt(len) is a
    // range outright (a random walk sits near 1.0); RANGE_BREAK_LOOKBACK is
    // how many recent candles are held back so the breakout is judged against
    // the box instead of being absorbed into it.
    'RANGE_ABS_COMPRESSION' => '0.75',
    'RANGE_BREAK_LOOKBACK' => '3',

    // Volume on the breaking candle as a multiple of the previous 20-candle
    // average. A break nobody participated in is a trap. Enforced as a hard
    // gate in ConfluenceProStrategy for continuation/breakout entries, not
    // just a scoring input.
    'BREAK_VOLUME_RATIO' => '1.3',

    // How far past the broken level price may already be, in ATR, before
    // taking the entry counts as chasing.
    'MAX_CHASE_ATR' => '1.5',

    // How far a coin must have run off its floor before a short counts as
    // fading a pump rather than shorting a base.
    'REVERSAL_RUN_PCT' => '12',

    // How tight the last 40 candles must be, as a percent of price, to call
    // the coin "based" and take its breakout.
    'BASE_RANGE_PCT' => '12',

    // Refuse an entry that has no order block or FVG behind it to put the
    // stop against. This is the single biggest win-rate lever here.
    'REQUIRE_ZONE_CONFLUENCE' => 'true',

    // Refuse a pullback/reversal entry (buying a demand zone after a drop,
    // selling a supply zone after a rally) unless a real hammer/shooting-
    // star candle just confirmed the turn on REVERSAL_CONFIRM_TIMEFRAME.
    // This is what stops the bot from going long on a coin purely because
    // it "already dropped a lot" with nothing on the chart having turned.
    'REQUIRE_REVERSAL_CANDLE' => 'true',
    'REVERSAL_CONFIRM_TIMEFRAME' => '5m',

    // Refuse a pullback/reversal entry when the zone its stop leans on
    // already existed before the liquidity sweep that is supposed to
    // justify the reversal. A real reversal is a specific chain: a stop
    // pool gets swept, price reverses, and that reversal leg itself leaves
    // behind the order block/FVG/supply-demand zone the trade retraces
    // into -- born DURING the reversal, not before it. A zone that predates
    // the sweep is real, but it is evidence for a different move, not this
    // one. Only checked when there is a sweep to anchor to; continuation/
    // breakout entries have no such narrative and are not affected.
    'REQUIRE_FRESH_REVERSAL_ZONE' => 'true',

    // Requires the liquidity sweep itself to have happened AT the zone
    // (order block/support-resistance/supply-demand) the reversal's stop
    // leans on -- a sharp, violent (funding-squeeze/liquidation-style)
    // move that sweeps a level and rejects right where a key zone
    // already sits, not two nearby but unrelated events. Only checked
    // when there is a sweep to anchor to.
    'REQUIRE_SWEEP_AT_ZONE' => 'true',

    // A hard reject, not just a missed scoring bonus: if the single
    // strongest support/resistance zone within reach has been respected at
    // least this many times AND sits against the trade's chosen direction,
    // the signal is refused outright -- regardless of how many other,
    // smaller/correlated signals voted for it. This is what stops a short
    // from ever publishing straight into a support line the chart has
    // clearly respected for months.
    'STRONG_ZONE_VETO_TOUCHES' => '3',

    // A second, independent-evidence-counting gate on top of confluence_pro's
    // own capped-group score: of seven separate confirmations (HTF trend,
    // liquidity sweep, CHoCH/BOS, fresh order block/FVG, an actual
    // break-and-retest pattern, displacement+volume, room to target), at
    // least APLUS_MIN_CONFIRMATIONS must be present, and a handful of setup
    // shapes (entry from dead-center of a range) are refused outright. See
    // APlusSetupFilter. Eased from 5 to 4 -- 5 rejected too much.
    'REQUIRE_APLUS_SETUP' => 'false',
    'APLUS_MIN_CONFIRMATIONS' => '3',

    // A manually-entered blackout window (free text, parsed with strtotime,
    // e.g. "2026-09-20 16:00") during which no new signal is generated at
    // all -- for a known high-impact release (CPI/FOMC/NFP/rate decision)
    // the operator wants to sit out. There is no live economic calendar
    // wired into this project; the operator enters the window themselves
    // from the panel. Both empty (the default) means always off.
    'NEWS_BLACKOUT_START' => '',
    'NEWS_BLACKOUT_END' => '',

    // ---------------------------------------------------------------------
    // Scanner buckets — the universe is built from three baskets rather
    // than one volume ranking, so every pass contains the day's biggest
    // movers in BOTH directions as well as the steady liquid names.
    // ---------------------------------------------------------------------
    'SCANNER_GAINER_SHARE' => '40',
    'SCANNER_LOSER_SHARE' => '25',
    // A coin has to have actually moved this much in 24h to count as a
    // mover; in a flat market the mover baskets simply come up short and
    // the liquid basket fills the rest.
    'SCANNER_MIN_MOVE_PCT' => '4',

    // ---------------------------------------------------------------------
    // Money management — the bot does not place orders, so these turn the
    // stop into the numbers the reader needs to size the trade.
    // ---------------------------------------------------------------------
    // Reference account the suggested position is calculated from.
    'ACCOUNT_BALANCE' => '1000',
    // Percent of it put at risk on one trade. Position size is derived from
    // this and the stop distance, never from the leverage.
    'RISK_PER_TRADE_PCT' => '2',
    // The daily circuit breaker: after this many stop-outs, or this many
    // published signals, the bot goes quiet until tomorrow. 0 = no cap.
    'MAX_DAILY_LOSSES' => '3',
    'MAX_DAILY_SIGNALS' => '8',

    // ---------------------------------------------------------------------
    // Signal cards (the images posted with every signal)
    // ---------------------------------------------------------------------
    'SIGNAL_CARD_ENABLED' => 'true',
    // 2 is the default; 3 is slightly smoother and roughly twice as slow.
    'CARD_RENDER_SCALE' => '2',
    // Wordmark printed in the card footer.
    'CARD_BRAND' => 'AUTO TRADE MARKET',
    // Printed under the trade-result card's footer line, e.g. '@yourchannel'.
    'CARD_HANDLE' => '',
    // Brand logo drawn on the cards. Leave empty and simply drop a PNG named
    // logo.png (transparent background works best) beside the PHP files; set
    // this only to keep it somewhere else. With no logo at all the cards fall
    // back to a drawn mark.
    'CARD_LOGO_PATH' => '',
    // Fonts. Leave these empty to use the Vazirmatn-*.ttf shipped alongside
    // the PHP files, which is what lets the cards carry Persian labels. Set
    // them to point at any other .ttf you prefer. If neither the configured
    // font nor the bundled one is readable (or FreeType is missing), the
    // cards fall back to the vector font built into card.php and switch
    // their labels to English.
    'CARD_FONT_PATH' => '',
    'CARD_FONT_PATH_BOLD' => '',

    // ---------------------------------------------------------------------
    // Runtime
    // ---------------------------------------------------------------------
    // LIVE actually posts to Telegram; DRY_RUN generates and queues only.
    // Also switchable from the panel (🎯 تنظیمات Signal).
    'RUN_MODE' => 'LIVE',

    // 'cron'   — one bounded pass per invocation, re-run by a cron job every
    //            minute. The only mode plain shared hosting can run.
    // 'daemon' — loops until SIGTERM. Needs a host that allows a persistent
    //            background process.
    'WORKER_MODE' => 'cron',
    'WORKER_MAX_RUNTIME_SECONDS' => '50',
    'WORKER_TICK_SECONDS' => '15',

    'HTTP_TIMEOUT_SECONDS' => '10',
    'MAX_RETRIES' => '5',
    'APP_TIMEZONE' => 'UTC',

    // ---------------------------------------------------------------------
    // Logging — the bot is meant to run silently. 'error' keeps the log
    // table and cron mail empty unless something is actually wrong; set
    // 'info' temporarily when diagnosing.
    // ---------------------------------------------------------------------
    'LOG_LEVEL' => 'error',
    'LOG_RETENTION_DAYS' => '3',

    // Write detected zones/order-blocks/FVGs to their tables. Nothing reads
    // them back; it is a debugging aid and it is the most expensive part of
    // a scan pass on SQLite.
    'PERSIST_STRUCTURE' => 'false',
];
