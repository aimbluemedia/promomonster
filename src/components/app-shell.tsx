import Link from "next/link";
import { Logo } from "@/components/logo";
import type { Session } from "@/lib/session";

export type NavItem = { href: string; label: string };

export function AppShell({
  session,
  nav,
  accent = "brand",
  children,
}: {
  session: Session;
  nav: NavItem[];
  accent?: "brand" | "earn";
  children: React.ReactNode;
}) {
  const dot = accent === "earn" ? "bg-earn" : "bg-brand";

  return (
    <div className="min-h-screen bg-paper">
      <header className="border-b border-line bg-surface">
        <div className="mx-auto flex h-14 w-full max-w-7xl items-center justify-between gap-4 px-5">
          <div className="flex items-center gap-6">
            <Link href="/" aria-label="PromoMonster home">
              <Logo />
            </Link>
            <nav className="hidden items-center gap-0.5 text-sm md:flex">
              {nav.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  className="rounded-md px-3 py-1.5 font-medium text-muted transition-colors hover:bg-raised hover:text-ink"
                >
                  {item.label}
                </Link>
              ))}
            </nav>
          </div>
          <div className="flex items-center gap-2.5 text-sm">
            <span
              aria-hidden="true"
              className={`h-2 w-2 rounded-full ${dot}`}
            />
            <span className="text-muted">{session.name}</span>
          </div>
        </div>
        <nav className="flex gap-0.5 overflow-x-auto border-t border-line px-3 py-1.5 text-sm md:hidden">
          {nav.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="whitespace-nowrap rounded-md px-3 py-1.5 font-medium text-muted hover:text-ink"
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </header>
      <div className="mx-auto w-full max-w-7xl px-5 py-8">{children}</div>
    </div>
  );
}

export function PageHeading({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="mb-7 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description && (
          <p className="mt-1.5 max-w-2xl text-[15px] text-muted">
            {description}
          </p>
        )}
      </div>
      {action}
    </div>
  );
}

/** Marks screens whose data is illustrative, so nobody mistakes it for live state. */
export function SampleDataNotice({ children }: { children: React.ReactNode }) {
  return (
    <div className="mb-6 rounded-lg border border-dashed border-line bg-raised px-4 py-3 text-sm text-muted">
      <strong className="font-semibold text-ink">Sample data.</strong>{" "}
      {children}
    </div>
  );
}
