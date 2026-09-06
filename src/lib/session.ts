import { cookies } from "next/headers";

export type Role = "business" | "panelist" | "admin";

export type Session = {
  email: string;
  name: string;
  role: Role;
};

export const SESSION_COOKIE = "pm_session";

/**
 * Phase 0 stand-in for authentication.
 *
 * This is NOT real auth. The cookie is unsigned and trivially forged, so it
 * exists only to make the members and admin areas navigable while the shell is
 * built out. docs/00-execution-plan.md §4 has auth as a buy-don't-build item
 * (Clerk or Supabase Auth) landing in Phase 1; this is deleted at that point.
 *
 * Enabling it requires PM_DEV_AUTH=1 and is refused outright in production, so
 * a misconfigured deploy fails closed rather than exposing the admin area.
 */
export function devAuthEnabled() {
  return process.env.NODE_ENV !== "production" && process.env.PM_DEV_AUTH === "1";
}

export async function getSession(): Promise<Session | null> {
  if (!devAuthEnabled()) return null;
  const raw = (await cookies()).get(SESSION_COOKIE)?.value;
  if (!raw) return null;
  try {
    const parsed = JSON.parse(raw) as Session;
    if (!parsed?.email || !parsed?.role) return null;
    if (!["business", "panelist", "admin"].includes(parsed.role)) return null;
    return parsed;
  } catch {
    return null;
  }
}

export async function requireSession(roles?: Role[]): Promise<Session | null> {
  const session = await getSession();
  if (!session) return null;
  if (roles && !roles.includes(session.role)) return null;
  return session;
}
