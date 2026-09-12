-- The product pivoted from a research panel to a reviews platform. These tables
-- belonged to the panel and have no equivalent here.
--
-- Safe on a fresh install (nothing to drop) and on a server that already ran
-- migrations 002-005 (which are no longer shipped).
--
-- Foreign key checks are disabled for the teardown rather than relying on drop
-- order: the panel tables reference each other in both directions (risk_events
-- points at task_slots, which points at campaigns), so no single ordering
-- satisfies every constraint.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS responses;
DROP TABLE IF EXISTS task_slots;
DROP TABLE IF EXISTS campaign_questions;
DROP TABLE IF EXISTS campaigns;
DROP TABLE IF EXISTS ledger_entries;
DROP TABLE IF EXISTS ledger_transactions;
DROP TABLE IF EXISTS ledger_accounts;
DROP TABLE IF EXISTS payouts;
DROP TABLE IF EXISTS payout_batches;
DROP TABLE IF EXISTS risk_events;
DROP TABLE IF EXISTS user_devices;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS panel_profiles;
DROP TABLE IF EXISTS panelists;
DROP TABLE IF EXISTS businesses;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;
