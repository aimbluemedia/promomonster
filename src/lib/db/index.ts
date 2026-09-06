import { drizzle } from "drizzle-orm/postgres-js";
import postgres from "postgres";
import * as schema from "./schema";

let client: postgres.Sql | undefined;
let database: ReturnType<typeof drizzle<typeof schema>> | undefined;

/**
 * Returns a Drizzle client, or null when DATABASE_URL is unset.
 *
 * Null is a supported state in development so the site runs with zero setup —
 * callers fall back to the local file store. In production the waitlist route
 * treats a null database as a hard error rather than silently writing to disk.
 */
export function getDb() {
  if (!process.env.DATABASE_URL) return null;
  if (!database) {
    client = postgres(process.env.DATABASE_URL, { max: 1 });
    database = drizzle(client, { schema });
  }
  return database;
}

export { schema };
