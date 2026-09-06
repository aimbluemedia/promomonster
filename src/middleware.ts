import { NextResponse, type NextRequest } from "next/server";
import { SESSION_COOKIE } from "@/lib/session";

/**
 * Gates the members (/app) and admin (/admin) areas.
 *
 * Fails closed: without PM_DEV_AUTH the areas are unreachable, and the flag is
 * ignored entirely in production (see lib/session.ts). Middleware can only read
 * the cookie's presence — role checks happen in each area's layout, server-side.
 */
const PROTECTED = [/^\/app(\/|$)/, /^\/admin(\/|$)/];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  if (!PROTECTED.some((pattern) => pattern.test(pathname))) {
    return NextResponse.next();
  }

  const devAuth =
    process.env.NODE_ENV !== "production" && process.env.PM_DEV_AUTH === "1";
  if (!devAuth) {
    return new NextResponse("Not found", { status: 404 });
  }

  if (!request.cookies.get(SESSION_COOKIE)) {
    const url = request.nextUrl.clone();
    url.pathname = "/signin";
    url.searchParams.set("next", pathname);
    return NextResponse.redirect(url);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/app/:path*", "/admin/:path*"],
};
