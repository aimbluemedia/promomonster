import { appendFile, mkdir } from "node:fs/promises";
import path from "node:path";
import { getDb, schema } from "./db";
import type { WaitlistInsert } from "./db/schema";

const FALLBACK_DIR = path.join(process.cwd(), ".data");
const FALLBACK_FILE = path.join(FALLBACK_DIR, "waitlist.jsonl");

export class WaitlistStorageError extends Error {}

/**
 * Persists a waitlist signup.
 *
 * With DATABASE_URL set, inserts into Postgres and treats a repeat
 * email+role as success (the visitor doesn't need to know they already
 * signed up, and it shouldn't read as an error).
 *
 * Without DATABASE_URL, appends to .data/waitlist.jsonl so Phase 0 runs with
 * no infrastructure — but only outside production, where a missing database
 * is a misconfiguration rather than a convenience.
 */
export async function saveWaitlistEntry(entry: WaitlistInsert) {
  const db = getDb();

  if (!db) {
    if (process.env.NODE_ENV === "production") {
      throw new WaitlistStorageError("DATABASE_URL is not configured");
    }
    await mkdir(FALLBACK_DIR, { recursive: true });
    await appendFile(
      FALLBACK_FILE,
      JSON.stringify({ ...entry, createdAt: new Date().toISOString() }) + "\n",
      "utf8",
    );
    return { stored: "file" as const };
  }

  await db.insert(schema.waitlist).values(entry).onConflictDoNothing({
    target: [schema.waitlist.email, schema.waitlist.role],
  });
  return { stored: "database" as const };
}

export type WaitlistRow = WaitlistInsert & { createdAt?: string | Date };

/**
 * Reads waitlist signups for the admin area, newest first.
 * Mirrors saveWaitlistEntry: Postgres when configured, the local file otherwise.
 */
export async function listWaitlist(limit = 200): Promise<WaitlistRow[]> {
  const db = getDb();

  if (!db) {
    try {
      const { readFile } = await import("node:fs/promises");
      const raw = await readFile(FALLBACK_FILE, "utf8");
      return raw
        .split("\n")
        .filter(Boolean)
        .map((line) => JSON.parse(line) as WaitlistRow)
        .reverse()
        .slice(0, limit);
    } catch {
      return [];
    }
  }

  const { desc } = await import("drizzle-orm");
  return db
    .select()
    .from(schema.waitlist)
    .orderBy(desc(schema.waitlist.createdAt))
    .limit(limit);
}
