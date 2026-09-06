# PromoMonster

**Real People. Real Answers.**

A consumer research panel. Businesses find out what real people think of their
website, content and ad creative; panel members get paid for honest opinions.

Planning and build documentation lives in [`docs/`](docs/) — start with
[`docs/00-execution-plan.md`](docs/00-execution-plan.md).

---

## The three sections

| Section | Routes | Purpose |
|---|---|---|
| **Public site** | `/`, `/business`, `/earn`, `/services/*` | Explains the product, captures waitlist signups |
| **Members area** | `/app/*` | Businesses run studies and buy credits; panel members work studies and get paid |
| **Superadmin** | `/admin/*` | Waitlist, study approvals, members, payouts, fraud |

Each is a Next.js route group with its own layout: `src/app/(site)`,
`src/app/(members)`, `src/app/(admin)`.

## What's real right now

Phase 0 per the execution plan, so the public site is fully working and the
platform is scaffolding:

- **Working end to end** — public site, waitlist capture with validation, rate
  limiting and a honeypot, and the admin waitlist view reading live signups.
- **Scaffolded** — members and admin screens have the real information
  architecture, navigation and access control, with clearly labelled sample
  data where the Phase 1 tables aren't built yet. Every such screen names the
  doc section that specifies it.

This is deliberate: [`docs/00-execution-plan.md`](docs/00-execution-plan.md) §3
gates platform work behind 20 hand-sold studies. The site is what you need on
day one; the rest grows into it rather than being thrown away.

## Running it

```bash
npm install
npm run dev            # http://localhost:3000
```

The waitlist works with no database — signups append to `.data/waitlist.jsonl`.
Set `DATABASE_URL` to use Postgres instead:

```bash
cp .env.example .env.local   # add your DATABASE_URL
npm run db:push
```

In production `DATABASE_URL` is required; the file fallback is refused so a
misconfigured deploy fails loudly instead of writing signups to a container
disk that gets thrown away.

### Members and admin areas

These are gated behind a development flag and are **404 without it**:

```bash
PM_DEV_AUTH=1 npm run dev
```

Then visit `/signin` and pick a role.

`src/lib/session.ts` is a placeholder, not authentication — the cookie is
unsigned and trivially forged. It exists so the areas can be built and
reviewed, it is refused outright in production, and it gets deleted when real
auth (Clerk or Supabase Auth) lands in Phase 1.

## Scripts

| Command | Does |
|---|---|
| `npm run dev` | Development server |
| `npm run build` | Production build |
| `npm run typecheck` | `tsc --noEmit` |
| `npm run db:generate` | Generate Drizzle migrations |
| `npm run db:push` | Push schema to the database |

## Stack

Next.js 15 (App Router) · TypeScript · Tailwind CSS v4 · Drizzle ORM ·
Postgres. Buy-don't-build choices for Phase 1 are listed in
[`docs/00-execution-plan.md`](docs/00-execution-plan.md) §4.
