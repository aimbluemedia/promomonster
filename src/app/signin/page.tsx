import { notFound, redirect } from "next/navigation";
import { cookies } from "next/headers";
import { Card, Container } from "@/components/ui";
import { devAuthEnabled, SESSION_COOKIE, type Role } from "@/lib/session";

export const metadata = { title: "Sign in" };

async function signIn(formData: FormData) {
  "use server";
  if (!devAuthEnabled()) notFound();

  const role = String(formData.get("role") ?? "business") as Role;
  const next = String(formData.get("next") ?? "");
  const session = {
    email: `${role}@promomonster.test`,
    name: role === "admin" ? "Admin" : role === "business" ? "Acme Pools" : "Sam R.",
    role,
  };

  (await cookies()).set(SESSION_COOKIE, JSON.stringify(session), {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
    maxAge: 60 * 60 * 8,
  });

  redirect(next && next.startsWith("/") ? next : role === "admin" ? "/admin" : "/app");
}

export default async function SignInPage({
  searchParams,
}: {
  searchParams: Promise<{ next?: string }>;
}) {
  if (!devAuthEnabled()) notFound();
  const { next } = await searchParams;

  const roles: { role: Role; label: string; desc: string }[] = [
    { role: "business", label: "Business", desc: "Runs studies, buys credits" },
    { role: "panelist", label: "Panel member", desc: "Completes studies, gets paid" },
    { role: "admin", label: "Superadmin", desc: "Approvals, payouts, fraud" },
  ];

  return (
    <Container className="py-20">
      <div className="mx-auto max-w-lg">
        <Card>
          <h1 className="text-xl font-semibold tracking-tight">
            Development sign-in
          </h1>
          <p className="mt-2 text-[15px] leading-relaxed text-muted">
            This is a placeholder so the members and admin areas can be built
            and reviewed. It is not authentication — the cookie is unsigned, and
            the whole route is disabled in production. Real auth arrives in
            Phase 1.
          </p>
          <div className="mt-6 space-y-3">
            {roles.map((entry) => (
              <form key={entry.role} action={signIn}>
                <input type="hidden" name="role" value={entry.role} />
                <input type="hidden" name="next" value={next ?? ""} />
                <button
                  type="submit"
                  className="flex w-full items-center justify-between rounded-lg border border-line bg-paper px-4 py-3 text-left transition-colors hover:bg-raised"
                >
                  <span>
                    <span className="block text-sm font-semibold">
                      {entry.label}
                    </span>
                    <span className="block text-xs text-muted">
                      {entry.desc}
                    </span>
                  </span>
                  <span aria-hidden="true" className="text-muted">
                    →
                  </span>
                </button>
              </form>
            ))}
          </div>
        </Card>
      </div>
    </Container>
  );
}
