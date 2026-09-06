import { notFound } from "next/navigation";
import { AppShell, type NavItem } from "@/components/app-shell";
import { getSession } from "@/lib/session";

const nav: NavItem[] = [
  { href: "/admin", label: "Overview" },
  { href: "/admin/waitlist", label: "Waitlist" },
  { href: "/admin/studies", label: "Approvals" },
  { href: "/admin/members", label: "Members" },
  { href: "/admin/payouts", label: "Payouts" },
  { href: "/admin/fraud", label: "Fraud" },
];

export default async function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const session = await getSession();
  if (!session || session.role !== "admin") notFound();

  return (
    <AppShell session={session} nav={nav}>
      {children}
    </AppShell>
  );
}
