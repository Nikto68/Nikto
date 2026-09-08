-- Order blocks and FVGs are upserted by their natural key on every scan
-- (same symbol/timeframe/type/origin candle = the same structure), so a
-- unique key is needed for INSERT ... ON DUPLICATE KEY UPDATE to work —
-- this also keeps `mitigated`/`filled` sticky across scans instead of
-- being reset by re-detection. Zones have no single origin candle (they
-- are ranges built from several methods), so they stay a replace-active-
-- set-per-scan model and do not need one.

ALTER TABLE order_blocks
    ADD UNIQUE KEY uq_order_blocks_natural (symbol_id, timeframe, type, origin_candle_open_time);

ALTER TABLE fvgs
    ADD UNIQUE KEY uq_fvgs_natural (symbol_id, timeframe, type, origin_candle_open_time);
