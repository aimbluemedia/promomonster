# PromoMonster — Business Plan & Build Package

Working documents for PromoMonster.com. Read in order.

| Doc | What it covers |
|---|---|
| **[00-execution-plan.md](00-execution-plan.md)** | **Start here.** Solo founder, sub-$25k budget, week-by-week |
| [01-strategy.md](01-strategy.md) | Positioning, what we sell, competitive frame, GTM |
| [02-unit-economics.md](02-unit-economics.md) | Credit pricing, margin math, panel supply model |
| [03-product-spec.md](03-product-spec.md) | Product scope, screens, task lifecycle, verification, fraud |
| [04-data-model.md](04-data-model.md) | Schema, money ledger, task leasing, indexes |
| [05-risk-compliance.md](05-risk-compliance.md) | Legal register, payments, tax, privacy, trademark |
| [06-platform-enforcement.md](06-platform-enforcement.md) | Why paid social/search views were cut, platform by platform |
| [07-social-product-design.md](07-social-product-design.md) | How to show panelists social content legitimately — what to build instead |

**Operating constraints these documents assume:** solo founder building it, under $25,000
of Year-1 cash. [00-execution-plan.md](00-execution-plan.md) is the plan that fits those;
docs 01–05 describe the destination and the full-scope product.

---

## What changed from the first draft, and why

The original draft is a good brand and a good pile of ideas. It is not yet a plan you
can build, because three of its load-bearing assumptions don't hold. Here is every
material change, with the reason.

### 1. Research is the business. Traffic is the wedge.

The draft treats "human feedback" as product #5 out of six. Run the arithmetic and it
is the only product that pays for a company:

| | Traffic | Research |
|---|---|---|
| Revenue per unit | $0.17 | $1.10 |
| Panelist cost per unit | $0.08 | $0.35 |
| Gross margin | ~53% | ~68% |
| Gross profit per active panelist / month | **~$6.80** | **~$34.50** |
| What the panelist earns per hour | ~$4.80 | ~$14.00 |

Same panel. Research produces **~5x the gross profit per panelist** and pays that
panelist **~3x better per hour**, which is what actually solves retention. It also needs
roughly a quarter of the panel to reach a given revenue number — which is the difference
between a cold start you can execute and one you can't. Full derivation in
[02-unit-economics.md](02-unit-economics.md).

So: lead with **PromoMonster Panel** (paid human research and feedback). Keep traffic as
the cheap, easy-to-explain acquisition product that gets an SMB to enter a card.

### 2. Three of the six proposed products are cut or rebuilt

Not on taste — on the fact that they transfer risk onto your paying customer.
[06-platform-enforcement.md](06-platform-enforcement.md) walks through each platform's
enforcement mechanics, who absorbs the penalty, and a worst-case incident.

- **Social discovery (YouTube/TikTok/IG/Reddit/X) — cut from MVP.** The draft's
  "discovery, not engagement" line is a distinction those platforms do not recognize.
  Paid views are incentivized traffic under YouTube's fake-engagement policy regardless of
  whether a like is requested; Reddit treats coordinated paid activity as manipulation.
  The party that eats the strike is *your customer's channel*. Replaced with **Social
  Creative Testing**: show panelists a real thumbnail/title/hook, ask which they'd click
  and why. Same insight, no ToS exposure, sells for ~10x more.
- **Search-result clicking (the SerpClix model) — cut.** It manipulates ranking signals,
  and the manual-action risk lands on the client's domain, not yours. That is a liability
  you are selling to people who don't understand they're buying it. The draft's own
  "search result testing" alternative is kept and promoted to a headline product.
- **Traffic to any site running ad-network monetization — hard-blocked.** Incentivized
  traffic is invalid traffic under Google's publisher policies; the *publisher* gets
  terminated. Sending 1,000 paid visits to a customer's AdSense site can end their
  account. This needs to be a signup question, a policy, and an automated check.

### 3. Traffic customers get an analytics tag, or an unverified/cheap tier

The draft's verification — a timer in our tab plus a comprehension question — cannot
prove time on site. Open tab, alt-tab, come back, skim for three seconds, answer. You'd
be selling an unverifiable claim.

Fix: a **PromoMonster tag** (GTM snippet + WordPress plugin) on the destination. It
reports real dwell, scroll depth and a signed nonce; it also auto-tags the traffic so it
can be excluded from the customer's GA4 rather than poisoning their engagement metrics
and Smart Bidding signals. No tag = "unverified" tier, priced lower, sold as best-effort.
Research tasks need no tag at all — the answer *is* the deliverable — which is a fourth
reason to lead with research.

### 4. Pricing rebuilt; the volume-discount curve was eating the company

At the draft's 5,000-credit pack ($349), a credit is $0.0698 and a basic visit pays the
worker $0.05 — a **28% gross margin before Stripe and payout fees**, i.e. roughly break-even
on your largest customers. Meanwhile a feedback response is priced at ~$0.44 against a
market that pays $1.50–$3.00 for the same thing.

New rule, enforced in the pricing table: **credits × floor price ≥ 2.4 × panelist payout**,
floor price $0.070, max volume discount 30%. Every task type re-priced against it.

### 5. The revenue targets are replaced with a bottom-up model

"500 paying customers and 100,000 tasks in months 1–3" from a cold start, with no brand,
in a category buyers are suspicious of, is not a target — it's a number that will cause
you to hire and spend against revenue that doesn't arrive. Replaced with a month-by-month
build from CAC, conversion and churn: **~$215k Year-1 revenue, ~$594k exit ARR, roughly
break-even with founder labor uncosted.** That plan you can actually staff.

### 6. Don't launch a marketplace. Launch a service.

The draft acknowledges the chicken-and-egg problem and then proposes solving it with
self-serve software. Faster path: recruit 400–800 panelists, sell the first 20 research
studies by hand, fulfil them yourself, and only build self-serve once you know what
buyers ask for. Concierge first is in the roadmap as Phase 0.

### 7. Panel profiling was missing, and it's the moat

Nowhere does the draft collect demographics. Without them you sell untargeted responses
at $1. With them — age, ZIP, income band, homeowner/renter, vehicle, pets, B2B job
function, purchase intent — you sell *"200 Arizona homeowners aged 35–60 with a pool"* at
$4–6 a response, and nobody can undercut you without rebuilding the panel. Pay $0.50 for
profile completion. This is the single highest-ROI addition to the plan.

### 8. Money handling was a bug factory

`amount` floats, a `balance` column on the earner row, and no idempotency. Replaced with
integer cents, a **double-entry ledger** with derived balances, idempotency keys on every
mutation, and task **leases** (TTL + atomic claim) instead of assignment — otherwise two
panelists get the same slot under concurrency. See [04-data-model.md](04-data-model.md).

### 9. Compliance section added from zero

The draft has no legal content. Added: contractor classification and 1099 handling (note:
the threshold moved to $2,000 for 2026 payments — confirm with your CPA), SaaS sales tax,
stored-value and escheatment exposure on held balances, GDPR/CCPA implications of device
fingerprinting, TCPA exposure in the Leads product (recommendation: **email-only, no phone,
at MVP**), the FTC's 2024 reviews rule (>$50k per violation for facilitating fake reviews —
so review platforms go on a hard URL blocklist), and processor risk: "paid to click" is a
category Stripe and PayPal shut down. Positioning as a market-research panel is not just
marketing — it's materially better odds of keeping your payment rails.

### 10. Trademark flag

"Monster" marks in advertising and promotion services draw opposition from well-funded
enforcers. You already have SearchMonster and MonsterList, so the exposure is portfolio-wide.
Get a clearance search and an attorney opinion **before** spending on the brand, not after.

---

## What was kept

Almost all of the brand work. `PromoMonster.com`, the two-sided homepage split, "Real
People. Real Traffic. Real Discovery.", "Earners" over "clickers", the credits abstraction,
the ecosystem tie-ins to SearchMonster / MonsterList / ContentVendor, the trust-score and
campaign-approval concepts, and the admin surface. Those were the right calls.
