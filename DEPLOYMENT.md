# Deploying PromoMonster

## If you're seeing "403 Forbidden — Access to this resource on the server is denied!"

That is **Apache/LiteSpeed's default error page**, which is what Hostinger,
cPanel and most shared hosts serve. It is not coming from this application —
there is no `403` anywhere in `src/`, and the only thing our middleware denies
with is a `404`.

What it means: the web server was pointed at a directory, found no
`index.html` in it, and has directory listing disabled — so it refuses rather
than serving anything. **The Next.js app is not running.**

The usual cause is uploading the repository (or the `.next` folder) into
`public_html` on shared hosting. That cannot work. Next.js is a Node
application, not a folder of HTML files. Something has to run `node` and keep
it running.

There is no `.htaccess` fix for this. Uploading a different set of files
won't help either. You need a host that runs Node.

---

## Option 1 — Vercel (recommended)

Made by the Next.js team, free for a project this size, and takes about five
minutes. This is what the app is built for.

1. Push the branch to GitHub (already done).
2. Go to vercel.com → **Add New → Project** → import `aimbluemedia/promomonster`.
3. Framework preset: **Next.js**. Everything else can stay on defaults —
   don't override the build command or output directory.
4. Add environment variables (below), then **Deploy**.
5. Add `promomonster.com` under **Settings → Domains** and point your DNS at
   the records Vercel gives you.

Every push to the branch redeploys automatically.

### Environment variables

| Variable | Needed | Notes |
|---|---|---|
| `DATABASE_URL` | **Yes in production** | Postgres connection string. Without it the app refuses to store waitlist signups rather than silently writing them to a disk that gets wiped. |
| `PM_DEV_AUTH` | No | Development only. Ignored in production by design — do not set it. |

For the database, Neon and Supabase both have free tiers that work fine here.
Create one, copy the connection string in, then run `npm run db:push` locally
with the same `DATABASE_URL` to create the `waitlist` table.

---

## Option 2 — Hostinger

**Shared hosting plans won't run this**, which is almost certainly what you hit.
Check whether your plan has a Node.js section in hPanel:

- **Node.js available** (Business, Cloud and some newer plans): create a Node app in hPanel, point it at the repo, set the start command to `npm run build && npm run start`, set the port hPanel gives you, and add the environment variables above.
- **No Node.js section** (basic shared hosting): the app cannot run there. Use Vercel, or upgrade to a Hostinger VPS where you control the stack.

If you use a VPS, add `output: "standalone"` to `next.config.ts` first — it
bundles only what the server needs and makes the deploy much smaller.

---

## Why not just export static HTML?

`next export` would produce files Apache can serve, and it would break the
things the site exists to do:

- **The waitlist API stops working.** `/api/waitlist` is a server route. Static export drops it, so the signup forms silently fail — and capturing signups is the entire point of the Phase 0 site.
- **Middleware stops working**, which is what gates `/app` and `/admin`.
- **The admin waitlist view stops working**, since it reads from the database at request time.

You'd be left with brochure pages that can't collect a single email. Not worth
it — put it on Vercel.

---

## Verifying a deploy worked

```bash
curl -o /dev/null -w "%{http_code}\n" https://your-domain/          # expect 200
curl -o /dev/null -w "%{http_code}\n" https://your-domain/business  # expect 200
curl -o /dev/null -w "%{http_code}\n" https://your-domain/app       # expect 404
curl -X POST https://your-domain/api/waitlist \
  -H 'Content-Type: application/json' \
  -d '{"email":"you@example.com","role":"business"}'                # expect {"ok":true}
```

`/app` returning **404** is correct — the members and admin areas are disabled
in production until real authentication lands in Phase 1.

If the last command returns a 500, `DATABASE_URL` is missing or wrong. Check
the deploy logs; the app logs `waitlist: storage not configured` for exactly
that case.
