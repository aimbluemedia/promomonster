# 00 — Execution Plan: Solo Founder, Under $25k

This is the operating plan given the two real constraints: **you are building it yourself**
and **total Year-1 cash is under $25,000.** The other documents describe the destination.
This one is what you do on Monday.

The earlier draft of this plan assumed an $85k contract developer and absorbed a ~$100k
Year-1 loss. Neither is available, so the sequencing changes substantially — and mostly
for the better, because the constraint forces you to sell before you build, which is the
correct order anyway.

## 1. The thing that makes $25k enough

**Credits are prepaid.** A customer pays $149 today; panelists are paid over the following
weeks, in batches, at a $10 minimum. You hold the cash in between.

That means your $25k is **not** funding cost of goods. Panel payouts are funded by revenue
that already landed. The $25k funds fixed costs, legal, panel acquisition and your tools —
and there is no working-capital hole to bridge.

Two rules protect this, and violating either is how prepaid-credit businesses die:

1. **Never spend against unearned credits.** Money for credits that haven't been consumed is a liability, not revenue. Keep it in a separate account and don't touch it.
2. **Track outstanding panelist liability as a real number** on the admin dashboard from week one. It's what you owe people who've worked. It is not yours.

## 2. Budget

| Line | Amount | Notes |
|---|---|---|
| Entity, registered agent, business banking | $500 | LLC; keep panel liability in a separate account |
| Legal — ToS, AUP, Privacy, Panelist Agreement | $2,000 | Reputable templates + a limited attorney review of the Panelist Agreement and AUP only |
| Trademark — knockout search + opinion | $1,000 | Do the USPTO search yourself first; pay for the opinion |
| Infrastructure Y1 | $1,500 | ~$100/mo: managed Postgres, Redis, hosting, email, domain |
| Panel acquisition | $4,000 | ~1,300 activated at ~$3, heavily supplemented by referral |
| Panel seeding — profile payments, pilot studies | $1,000 | Priming the panel before paying customers exist |
| Published research (marketing) | $2,500 | ~14 public studies at ~$175 of panel time each |
| Sales tooling — email finder, sending domain, outreach | $1,200 | |
| Design, analytics, misc SaaS | $1,000 | |
| Contingency | $5,000 | Do not pre-spend this |
| **Total** | **$19,700** | ~$5k unallocated headroom under the $25k cap |

Notable omissions: no contract development, no paid ads, no PR, no second entity for the
social/search products (deferred to year two per [06-platform-enforcement.md](06-platform-enforcement.md) §7).

**The single largest spend is panel acquisition, and it should be.** The panel is the asset.

## 3. Phase 0 — Concierge, weeks 1–10. Write almost no code.

The goal is 20 paid studies and 400 panelists **before you build the platform.** What
buyers ask for during these ten weeks is your actual specification, and it will not match
what you'd have guessed.

**Stack for this phase — all off-the-shelf:**

| Need | Tool | Cost |
|---|---|---|
| Landing page + waitlist | Next.js static or Carrd | ~$0 |
| Panelist signup | Tally form → Airtable | $0 |
| Study fielding | A Tally form per study, link emailed to matching panelists | $0 |
| Panelist payouts | PayPal Mass Payout CSV, weekly | $0.25/payout |
| Customer invoicing | Stripe Payment Links | 2.9% + $0.30 |
| Results delivery | Google Sheets → a formatted PDF you assemble | $0 |
| Panel database | Airtable | ~$20/mo |

**Week by week:**

- **1–2** — Entity, banking, domain, terms drafted. Two landing pages live: business side and panel side. Panel waitlist open.
- **3–4** — Recruit the first 200 panelists. Reddit (r/beermoney, r/WorkOnline — be straightforward there, they will audit you and their verdict sticks either way), Facebook side-hustle groups, survey aggregator listings. Pay $0.50 for profile completion. Run two free pilot studies to shake out the process.
- **5–6** — Sell the first five studies. Do it by hand: DM agencies on Reddit (r/PPC, r/SEO, r/marketing), LinkedIn, and your existing SearchMonster / MonsterList / ContentVendor customer lists — that last one is your warmest audience and costs nothing. Price at $149 for 100 responses to make the first yes easy.
- **7–8** — Deliver, iterate the question templates, publish your first public research study.
- **9–10** — Sell fifteen more. Raise to $199. Panel to 400. **Write down every question a buyer asked that you couldn't answer** — that list is the build spec.

**Phase 0 gate.** Do not start building until: 20 studies sold, ≥ 8 repeat buyers, and you
can state in one sentence what buyers consistently ask for. If you can't sell 20 studies by
hand, self-serve software will not fix that, and you'll have spent five months finding out.

## 4. Phase 1 — Minimum self-serve, months 3–8

Roughly **350–450 hours solo.** At 20 hours/week that's 18–22 weeks. Budget the calendar
honestly: you are also selling and running the panel during this period.

**Build exactly this:**

1. Auth (Clerk or Supabase Auth — do not write this)
2. Panelist: signup, 18+ gate, email verification, profile questionnaire, task feed, task runner, balance, payout request
3. Business: signup, Stripe credit packs, templated study builder, live results page, CSV export
4. Admin: campaign approval queue, flagged responses, payout batch export, panelist detail
5. Ledger (integer cents, double-entry) and task leasing — from day one, not retrofitted
6. Fraud v1: Turnstile, email verification, device fingerprint collision detection, attention checks, duplicate/gibberish text rules

**One task type: Site Feedback Study.** Head-to-Head is the same engine with two URLs — a
config flag, not a second feature.

**Explicitly cut from Phase 1** (all were in the earlier spec):

| Cut | Why | Interim |
|---|---|---|
| Subscriptions | Dunning, proration, upgrades — weeks of work | Credit packs only, via Stripe Checkout |
| Trust score | Needs data you don't have yet | Manual review + three hard rules |
| Referral program | Fraud surface before you can police it | Manual referral credits |
| PDF / white-label reports | Real work, no revenue yet | CSV export |
| LLM theme summaries | Nice, not load-bearing | Read the responses |
| Traffic products + the tag | Requires customer installs | Deferred to year two |
| Targeting | Needs panel scale to be sellable | Collect profiles now, sell targeting later |
| Browser extension | Not needed for research tasks | — |

**Buy, don't build:** auth, managed Postgres (Supabase/Neon), payouts and 1099 handling
(**Tremendous** — they handle W-9 collection, TIN matching and 1099 filing; building this
yourself at 1,000 panelists is a part-time job), email (Resend), bot defense (Cloudflare
Turnstile), analytics (PostHog free tier).

## 5. Phase 2 — months 8–12

Subscriptions, trust score, referral program, PDF and white-label reports, LLM summaries,
automated payouts, basic profile targeting. Driven by what Phase 1 customers actually ask
for, not by this list.

**Year two:** traffic products and the tag, agency accounts, API, leads, and only then a
revisit of the social/search question.

## 6. Revised Year-1 financials

Assumptions: concierge months 1–3 at higher ARPU (hand-sold custom studies), self-serve
live month 8, ARPU declining as smaller self-serve accounts mix in, 8% monthly logo churn,
55% gross margin, and you doing all sales and all engineering.

| Mo | Accounts | ARPU | Revenue | GP @55% |
|---|---|---|---|---|
| 1 | 2 | $350 | $700 | $385 |
| 2 | 5 | $350 | $1,750 | $963 |
| 3 | 9 | $340 | $3,060 | $1,683 |
| 4 | 14 | $320 | $4,480 | $2,464 |
| 5 | 20 | $280 | $5,600 | $3,080 |
| 6 | 27 | $250 | $6,750 | $3,713 |
| 7 | 36 | $220 | $7,920 | $4,356 |
| 8 | 48 | $200 | $9,600 | $5,280 |
| 9 | 64 | $190 | $12,160 | $6,688 |
| 10 | 84 | $180 | $15,120 | $8,316 |
| 11 | 108 | $175 | $18,900 | $10,395 |
| 12 | 138 | $170 | $23,460 | $12,903 |
| **Y1** | **138** | | **$109,500** | **$60,225** |

**Exit run-rate $23,460/mo ≈ $281k ARR.**

**Y1 gross profit $60,225 − $19,700 cash costs = ~$40,500** available as founder
compensation, mostly in the second half. Not a salary, but **cash-positive and
self-funding into year two** — which is the correct target for a bootstrap, and a
fundamentally different outcome from the $101k loss the funded version carried.

**Panel required at month 12:** ~21,300 responses/month ÷ ~30 per active panelist ≈
**700–1,100 active panelists.** Your $4,000 acquisition budget covers ~1,300 activated,
which at typical retention sustains that. The numbers cohere.

### Downside case — assume you hit half

$55,000 revenue, ~$30,000 gross profit, $19,700 costs, **~$10,000 net.** You'd have a
working platform, a real panel, and paying customers, having spent almost nothing. That is
a survivable miss, which is the point of sequencing it this way.

### The assumption most likely to be wrong

Reaching 138 accounts means roughly 190 gross adds over the year, ~30/month by Q4, while
also being the entire engineering team. **That's the number to watch.** If new accounts
stall around 15/month, stop building features and spend the time on the agency partner
program and published research — those are the two channels that scale without proportional
founder hours.

## 7. What would make me tell you to stop

Honest kill criteria, decided now while it's cheap:

- **Week 10:** fewer than 10 paid studies sold by hand. Demand isn't there at this price, and the platform won't create it.
- **Month 6:** repeat-purchase rate under 30%. The research output isn't useful enough; fix the product before scaling the panel.
- **Month 8:** median active panelist earning under $5/month. The panel will churn out from under you regardless of demand.
- **Any time:** payment processor terminates you. Stop selling, pay every panelist their balance in full, then re-plan. Do not attempt to keep operating on a backup rail while the underlying reason is unresolved.

## 8. First five things

1. USPTO knockout search on "PromoMonster" in classes 35 and 42, today. Everything else is wasted if this fails. ([05-risk-compliance.md](05-risk-compliance.md) §7)
2. Form the LLC and open two bank accounts — operating, and panel liability.
3. Put up both landing pages and open the panel waitlist.
4. Recruit the first 100 panelists and pay them to complete profiles.
5. Sell one study, by hand, to someone already on a SearchMonster or MonsterList customer list.

Step 5 is the one that matters. Everything before it is setup.
