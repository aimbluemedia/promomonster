import {
  bigserial,
  index,
  pgEnum,
  pgTable,
  text,
  timestamp,
  uniqueIndex,
} from "drizzle-orm/pg-core";

/**
 * Phase 0 schema — waitlist capture only.
 *
 * The full platform schema (users, campaigns, task_slots, the double-entry
 * ledger, payouts, fraud tables) is specified in docs/04-data-model.md and
 * lands in Phase 1. Deliberately not built yet: see docs/00-execution-plan.md,
 * which gates platform work behind 20 hand-sold studies.
 */

export const waitlistRole = pgEnum("waitlist_role", ["business", "panelist"]);

export const waitlist = pgTable(
  "waitlist",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    email: text("email").notNull(),
    role: waitlistRole("role").notNull(),
    name: text("name"),
    // business side
    company: text("company"),
    website: text("website"),
    goal: text("goal"), // "what are you hoping to learn?" — feeds positioning research
    // panelist side
    country: text("country"),
    region: text("region"),
    postalCode: text("postal_code"),
    // provenance
    source: text("source"),
    referrer: text("referrer"),
    ip: text("ip"),
    userAgent: text("user_agent"),
    createdAt: timestamp("created_at", { withTimezone: true })
      .notNull()
      .defaultNow(),
  },
  (t) => [
    uniqueIndex("waitlist_email_role_idx").on(t.email, t.role),
    index("waitlist_created_at_idx").on(t.createdAt),
  ],
);

export type WaitlistInsert = typeof waitlist.$inferInsert;
