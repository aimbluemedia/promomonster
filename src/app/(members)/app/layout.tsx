import { notFound } from "next/navigation";
import { AppShell, type NavItem } from "@/components/app-shell";
import { getSession } from "@/lib/session";

const businessNav: NavItem[] = [
  { href: "/app", label: "Overview" },
  { href: "/app/studies", label: "Studies" },
  { href: "/app/credits", label: "Credits" },
];

const panelistNav: NavItem[] = [
  { href: "/app", label: "Overview" },
  { href: "/app/tasks", label: "Available studies" },
  { href: "/app/earnings", label: "Earnings" },
  { href: "/app/profile", label: "Profile" },
];

export default async function MembersLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const session = await getSession();
  if (!session || session.role === "admin") notFound();

  return (
    <AppShell
      session={session}
      nav={session.role === "business" ? businessNav : panelistNav}
      accent={session.role === "business" ? "brand" : "earn"}
    >
      {children}
    </AppShell>
  );
}
