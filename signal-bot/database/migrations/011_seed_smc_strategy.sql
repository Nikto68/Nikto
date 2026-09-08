-- Activates the real strategy translated from the user's combined SMC
-- Pine Script indicator (see src/Strategy/SmcStrategy.php). Weights are
-- configurable from the admin panel afterwards — this is only the seed.

INSERT INTO strategies (code, name, description, class_name, is_active, htf_timeframe, confirmation_timeframe, entry_timeframe, min_score, config)
VALUES (
    'smc_confluence',
    'SMC Confluence (S/R + BOS/CHoCH + Liquidity + Order Block)',
    N'ترجمه‌شده از اندیکاتور ترکیبی SMC کاربر: جهت معامله از Market Structure (BOS/CHoCH) تایم‌فریم 4H، ماشه از Order Block/Supply-Demand، Entry/SL روی لبه‌های ناحیه، TP در سطح بعدی Liquidity/S/R.',
    'App\\Strategy\\SmcStrategy',
    1,
    '4h',
    '1h',
    '15m',
    70,
    JSON_OBJECT(
        'timeframes', JSON_OBJECT('htf', '4h', 'confirmation', '1h', 'entry', '15m', 'daily', '1d', 'weekly', '1w'),
        'weights', JSON_OBJECT(
            'order_block', 25,
            'trend', 15,
            'structure_alignment', 20,
            'liquidity', 20,
            'support', 15,
            'resistance', 15
        ),
        'required_confluences', JSON_ARRAY('order_block'),
        'min_score', 70
    )
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    class_name = VALUES(class_name),
    config = VALUES(config);
