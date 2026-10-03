-- The public one-time comparison at /compare writes into `audits` alongside the
-- hand-run audit requests, so the operator sees one queue rather than two.
--
-- `source` is what tells them apart, and it is also the backstop for spend: the
-- daily cap counts self-serve rows, so a bad day cannot run up an unbounded
-- Claude bill. Without this column there is no way to count them.

ALTER TABLE audits
    ADD COLUMN source ENUM('audit_form','self_serve') NOT NULL DEFAULT 'audit_form' AFTER vertical,
    ADD COLUMN results_generated_at DATETIME NULL AFTER results,
    ADD KEY audits_source_index (source, created_at);

-- One free comparison per address. A partial index would express this better,
-- but MySQL has none, so the rule is enforced in the query and this index makes
-- that lookup cheap.
ALTER TABLE audits
    ADD KEY audits_email_source_index (email, source);
