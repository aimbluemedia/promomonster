# 03 — MVP Product Specification

Full product scope for the self-serve platform. The draft's "Phase 1" listed ~20 features
across six task types and would run 6+ months of contract development.

> **Solo-build scope note.** You're building this yourself on under $25k, so Phase 1 is a
> strict subset of this document — see [00-execution-plan.md](00-execution-plan.md) §4 for
> exactly what ships and what's cut (subscriptions, trust score, referrals, PDF reports,
> LLM summaries, all traffic products). Sections 5, 6 §"Automatic rejection", 7 and 9 here
> are load-bearing from day one; the rest phases in. Nothing in Phase 0 is built at all —
> it runs on Tally, Airtable and PayPal.

## 1. Ship criteria

A business can, without talking to anyone: sign up → buy credits → build a Site Feedback
or Head-to-Head study → have it approved → see 100 real responses inside 24 hours → export
them. A panelist can: sign up → complete a profile → work a task feed → get paid.

**Task types in MVP: two.** Site Feedback Study and Head-to-Head Test. Traffic is Phase 2
because it requires the tag. Cutting from six task types to two is what makes the timeline
real.

## 2. Roles

`panelist` · `business_owner` · `business_member` (Phase 3) · `admin` · `reviewer`

One `users` table, role on the membership. A person may hold both sides — allow it, but
never serve a panelist a task belonging to a business they are a member of.

## 3. Panelist flows

### 3.1 Signup
Email + password (magic link Phase 2). Collected: first/last name, email, country (US
only at launch), state, ZIP, DOB with **18+ hard gate**, ToS/privacy acceptance, PayPal
email (may be deferred to first payout).

Silently captured: IP, device fingerprint, user-agent, timezone, screen dimensions,
signup referrer. Disclosed in the privacy policy as fraud prevention.

Email verification is required before any task is served. No exceptions — it is the
cheapest fraud filter available.

### 3.2 Profile (do not skip this)
A one-time ~4-minute questionnaire, paid $0.50, credited after 5 approved tasks (so
profile-farming is unprofitable).

Captured: age band, gender, household income band, homeowner/renter, household size,
children at home, education, employment status, industry, job function, company size,
vehicle ownership, pets, and 8–12 category-intent flags (*planning home improvement in
6 months?* *shopping for insurance?*).

Re-prompted every 6 months for freshness. **This is the asset that lets you sell $4
responses instead of $1 ones.** Build it in the MVP even though nothing consumes it until
Phase 3.

### 3.3 Task feed
```
Balance $12.47   Pending $1.35   Lifetime $184.62   Trust 78
[ REQUEST PAYOUT ]

Available now
─────────────────────────────────────────────────────────
Website feedback — home services        $0.35   ~90 sec
Compare two headlines                   $0.42   ~2 min
Website feedback — online store         $0.35   ~90 sec
─────────────────────────────────────────────────────────
```
Ordered by: eligibility → trust-score tier → payout descending → campaign priority. Never
show a task the panelist can't take; an empty feed with a "come back later" message beats
a feed full of ineligible rows.

Eligibility filters: not already completed this campaign (hard unique constraint), profile
matches targeting, trust score above the campaign floor, geo match, device match, daily
per-panelist cap, and not the panelist's own business.

### 3.4 Task execution — Site Feedback Study
1. Panelist taps a task → server **leases** the slot (see §5). Lease TTL 15 minutes.
2. Instruction card renders. *"Spend at least 60 seconds on this site, then answer 4
   questions. Answer honestly — there are no right answers, and low-effort responses are
   rejected."*
3. `VIEW WEBSITE` opens the target in a new tab with a per-task token in the URL
   (`?pm_t=<token>`). Timer runs in the PromoMonster tab.
4. Questions unlock at the minimum dwell. Panelist answers.
5. Submit → response validated (§6) → task moves to `submitted`.
6. Payout to **pending** balance immediately, clearing to **available** after review
   (auto-approve at 24h if not flagged and the business hasn't rejected).

Explicitly stated to the panelist: *"We cannot see what you do on the destination site.
We check your answers."* Honest, and it sets the right expectation about how quality is
enforced.

### 3.5 Payouts
Minimum $10. Weekly batch, Friday. First payout held 7 days for fraud review. PayPal at
MVP (manual CSV export → PayPal Mass Payout); automated via API in Phase 2. Tremendous
(gift cards / Visa) added in Phase 2 as the hedge against PayPal account risk —
see [05-risk-compliance.md](05-risk-compliance.md) §4.

W-9 collected before the first payout that brings calendar-year earnings over $500
(threshold set below the reporting threshold deliberately, so you're never chasing a
panelist for a W-9 after the fact).

## 4. Business flows

### 4.1 Signup
Name, work email, password, company, primary website, industry, country.

**Two mandatory qualifying questions, on the signup form:**
- *"Does the site you want to promote display ads from Google AdSense, Ezoic, Mediavine, AdThrive or any other ad network?"* → Yes blocks traffic products entirely, with an explanation. Research products remain available.
- *"What are you hoping to learn or achieve?"* → free text, routes to the right product and feeds your positioning research.

### 4.2 Buy credits
Stripe Checkout. Packs and subscriptions per [02-unit-economics.md](02-unit-economics.md) §2.
Stripe Tax enabled from day one (SaaS is taxable in 20+ US states). Credits land in the
ledger on `checkout.session.completed` webhook — **never** on the client redirect.

### 4.3 Study builder
```
Step 1  What do you want to learn?
        ( ) First impressions of my website
        ( ) Which of two options people prefer
        ( ) Custom questions

Step 2  URL(s)          https://…            [validate + screenshot preview]

Step 3  Questions       [templates prefilled, editable]
        Q1  What do you think this company sells?          (short text)
        Q2  How clear was this page?                       (1–5)
        Q3  What would stop you contacting them?           (short text)
        Q4  How likely are you to contact them?            (1–5)
        + Add question   (max 6)

Step 4  Audience        General US population   |   Targeted (Phase 3)

Step 5  How many people?   50 · 100 · 250 · 500 · custom

Step 6  Review
        100 responses × 12 credits = 1,200 credits
        Balance after: 800    Est. complete: ~4 hours
        [ LAUNCH STUDY ]
```

Question types at MVP: short text, long text, single choice, multi choice, 1–5 rating,
yes/no. **At least one open-text question is mandatory** — it's the quality signal that
makes rejection defensible and the output buyers actually quote.

Templates matter more than the builder. Ship 8: first impression, message clarity,
pricing-page test, headline A/B, logo A/B, competitor comparison, checkout friction,
local-services trust.

### 4.4 Results
Live as responses arrive. Per question: distribution chart for closed questions; for
open text, the full list plus an LLM-generated theme summary (*"31 of 100 mentioned
pricing was unclear"*) — clearly labeled as an AI summary with raw responses always one
click away.

Export CSV and PDF. The PDF is the agency white-label artifact and is worth building well;
it's what gets forwarded to the agency's client and it carries your logo.

**Quality guarantee:** reject any response with a reason, get it re-fielded free. Capped at
20% of the study's responses; beyond that, rejections are flagged for admin review, since
a business rejecting everything is getting free work.

## 5. Task leasing (get this right or the ledger breaks)

Never "assign" a task. **Lease** it.

```
BEGIN;
  SELECT id FROM task_slots
   WHERE campaign_id = $1 AND state = 'open'
   ORDER BY id
   FOR UPDATE SKIP LOCKED
   LIMIT 1;
  UPDATE task_slots
     SET state='leased', panelist_id=$2,
         leased_at=now(), leased_until=now() + interval '15 minutes'
   WHERE id = $slot;
COMMIT;
```

`FOR UPDATE SKIP LOCKED` is the whole trick — it lets N concurrent panelists each grab a
distinct slot without serializing. A sweeper returns expired leases to `open` and
increments `attempt_count`; a slot that fails 5 attempts goes to `stalled` for admin
review.

Slots are pre-created at campaign launch and credits are **reserved** at that moment
(`reserved_credits`), not spent per completion. Unfilled slots at campaign end release
their reservation back to the balance. This prevents overspend and gives an exact,
defensible refund on partial fill.

**Fill-rate SLA:** if a study is under 90% filled at 72 hours, auto-release the remainder
and email the customer. Never silently leave a study half-filled — that's the #1 complaint
driver for panel businesses.

## 6. Response quality & fraud

### Automatic rejection (no human, no pay)
- Submitted before minimum dwell elapsed
- Open-text under 4 words, or matching a gibberish/keyboard-mash pattern
- Open-text identical to another response in the same campaign
- Failed attention check (one per study over 4 questions: *"Select 'Somewhat' for this question."*)
- Response time below the 5th percentile for that campaign

### Flagged for review (paid, but trust score drops)
- Very high AI-detection score on open text — treat as a signal, not proof; these
  classifiers have real false-positive rates and a wrongly-banned panelist writes a review
- Straight-lining every rating question
- Answers inconsistent with stated profile
- Device or IP shared with another account
- Datacenter IP / known VPN or proxy range

### Account-level signals
IP reputation, device fingerprint collisions, signup velocity from a subnet, impossible
geographic movement, referral rings (A refers B refers C with shared devices), payout email
reused across accounts, sudden behavior change after payout threshold.

### Trust score (0–100)
Start 50. Up: approved responses, tenure, profile completeness, consistency. Down:
rejections, flags, abandoned leases, fraud signals. Gates: < 30 no tasks; 30–49 low-value
only; 50–79 standard; 80+ premium and targeted studies, faster payout clearing.

**Ban policy:** always give a reason, always allow one appeal to a human. Earned balances
on a confirmed-fraud ban are forfeited, and that must be explicit in the ToS. Silent bans
with confiscated balances are how these companies get destroyed on Trustpilot and Reddit.

## 7. Campaign approval

Every campaign is reviewed before its first slot opens. Automated pre-checks:

- URL reachable, not on the blocklist, no malware flag (Google Safe Browsing API)
- Ad-network detection: fetch the page, scan for `adsbygoogle`, `googlesyndication`, Ezoic, Mediavine, AdThrive, Taboola, Outbrain markers → auto-reject traffic campaigns
- Domain blocklist: review platforms (Google Maps/Business, Yelp, Trustpilot, G2, Amazon reviews, App Store, Play Store), crypto/token sites, adult, MLM, payday/high-cost lending, gambling
- Social platform URLs → traffic products blocked, creative-testing products offered
- Question text scanned for prohibited asks (*"leave a review", "subscribe", "click the ad", "upvote"*)

Manual review for: first campaign from any account, any campaign over 500 responses, and
anything the automated checks flag. Target SLA 4 hours in business time.

States: `draft → pending_review → approved → active → (paused) → completed | rejected | cancelled`

## 8. Admin

- **Queues:** campaigns pending review · responses flagged · payouts pending · fraud alerts · panelist appeals
- **Business detail:** ledger, campaigns, credits, refund, suspend, note
- **Panelist detail:** trust score, task history, response samples, IP/device history, payout history, suspend, ban, adjust balance (every adjustment double-entry with a reason and an actor)
- **Financial:** MRR, credits sold vs consumed vs reserved, **outstanding panelist liability** (this is a real balance-sheet number — track it from day one), margin by task type
- **Kill switch:** pause all campaigns; pause all payouts

## 9. Tech

Next.js (App Router) + TypeScript + Tailwind · Postgres 16 · Redis (leases, rate limits,
queues) · Stripe + Stripe Tax · Resend or SES · Vercel or Fly.io · Cloudflare (WAF, bot
management, Turnstile on both signups) · Sentry · PostHog.

Non-negotiables:
- **Money is `bigint` cents. Never float. Anywhere.**
- Every money mutation is double-entry and carries an idempotency key
- All webhook handlers idempotent and signature-verified
- Row-level authorization tested — a panelist reading another panelist's responses, or a business reading another business's study, is the breach that ends this
- Nightly job reconciles derived balances against ledger sums and alarms on drift
- Immutable audit log on every admin action

## 10. Phase 2 — the PromoMonster tag

Traffic products need this; it's why they're not in the MVP.

A ~3KB script the customer installs via GTM or a WordPress plugin. On a visit carrying
`?pm_t=<token>` it: verifies the token against our API, records dwell, scroll depth,
pages viewed and engagement events, sets a `pm_traffic=1` GA4 event parameter so the
customer can filter our traffic out of their reporting, and posts a signed completion
beacon.

Three things it buys you: verification you can honestly sell, real analytics as a product
surface, and **it stops you poisoning your customer's GA4 and Smart Bidding signals** —
which is the quiet way an unverified traffic product loses customers who never tell you
why. Ship the GA4 exclusion instructions in onboarding, not buried in a help doc.

No tag = the "unverified" tier: cheaper, no delivery guarantee, labeled as such in the UI
and on the invoice.
