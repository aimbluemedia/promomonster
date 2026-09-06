import Link from "next/link";
import { Logo } from "@/components/logo";
import { Container } from "@/components/ui";

const nav = [
  { href: "/business", label: "For businesses" },
  { href: "/services/content", label: "Content" },
  { href: "/services/social", label: "Social" },
  { href: "/earn", label: "Earn" },
];

export default function SiteLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <header className="sticky top-0 z-50 border-b border-line bg-paper/85 backdrop-blur">
        <Container className="flex h-16 items-center justify-between gap-4">
          <Link href="/" aria-label="PromoMonster home">
            <Logo />
          </Link>
          <nav className="flex items-center gap-0.5 text-sm sm:gap-1">
            {nav.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="hidden rounded-md px-3 py-2 font-medium text-muted transition-colors hover:text-ink sm:block"
              >
                {item.label}
              </Link>
            ))}
            <Link
              href="/business#start"
              className="ml-1 rounded-lg bg-brand px-4 py-2 font-semibold text-white transition-opacity hover:opacity-90"
            >
              Start a study
            </Link>
          </nav>
        </Container>
      </header>

      <main id="main">{children}</main>

      <footer className="mt-24 border-t border-line py-12">
        <Container className="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <Logo />
            <p className="mt-3 max-w-md text-sm text-muted">
              Real people. Real answers. PromoMonster is a consumer research
              panel for businesses that want to know what their website,
              content and creative actually communicate.
            </p>
          </div>
          <nav className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted">
            {nav.map((item) => (
              <Link key={item.href} href={item.href} className="hover:text-ink">
                {item.label}
              </Link>
            ))}
          </nav>
        </Container>
        <Container className="mt-8 text-xs text-faint">
          © {new Date().getFullYear()} PromoMonster. Panel members are
          independent contractors, paid for their time and opinions.
        </Container>
      </footer>
    </>
  );
}
