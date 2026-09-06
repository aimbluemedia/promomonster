import { NextResponse } from "next/server";
import { z } from "zod";
import { rateLimit } from "@/lib/rate-limit";
import { saveWaitlistEntry, WaitlistStorageError } from "@/lib/waitlist";

const schema = z.object({
  email: z.string().trim().email().max(254),
  role: z.enum(["business", "panelist"]),
  name: z.string().trim().max(120).optional(),
  company: z.string().trim().max(160).optional(),
  website: z.string().trim().max(300).optional(),
  goal: z.string().trim().max(2000).optional(),
  country: z.string().trim().max(2).optional(),
  region: z.string().trim().max(80).optional(),
  postalCode: z.string().trim().max(16).optional(),
  source: z.string().trim().max(80).optional(),
  // Honeypot: a real person never fills a field they cannot see. Accept any
  // value here so a bot's submission reaches the silent-success branch below
  // rather than failing validation, which would tell it the field is checked.
  website_url: z.string().max(500).optional(),
});

function clientIp(request: Request) {
  const forwarded = request.headers.get("x-forwarded-for");
  if (forwarded) return forwarded.split(",")[0]!.trim();
  return request.headers.get("x-real-ip") ?? "unknown";
}

export async function POST(request: Request) {
  let payload: unknown;
  try {
    payload = await request.json();
  } catch {
    return NextResponse.json({ error: "Invalid request." }, { status: 400 });
  }

  const parsed = schema.safeParse(payload);
  if (!parsed.success) {
    return NextResponse.json(
      { error: "Please check the details and try again." },
      { status: 400 },
    );
  }

  const { website_url, ...data } = parsed.data;

  // Silently accept honeypot submissions so bots get no signal from the response.
  if (website_url) return NextResponse.json({ ok: true });

  const ip = clientIp(request);
  const limit = rateLimit(`waitlist:${ip}`, 5, 60 * 60 * 1000);
  if (!limit.ok) {
    return NextResponse.json(
      { error: "Too many signups from this connection. Try again later." },
      { status: 429 },
    );
  }

  try {
    await saveWaitlistEntry({
      ...data,
      email: data.email.toLowerCase(),
      ip,
      referrer: request.headers.get("referer") ?? undefined,
      userAgent: request.headers.get("user-agent")?.slice(0, 500) ?? undefined,
    });
  } catch (error) {
    if (error instanceof WaitlistStorageError) {
      console.error("waitlist: storage not configured", error);
    } else {
      console.error("waitlist: failed to save", error);
    }
    return NextResponse.json(
      { error: "Something went wrong on our end. Please try again." },
      { status: 500 },
    );
  }

  return NextResponse.json({ ok: true });
}
