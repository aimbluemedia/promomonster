# 05 — Risk & Compliance

> **Not legal or tax advice.** This is an issue register so you know what to take to
> counsel and to a CPA, and roughly what it will cost. Every item marked **[counsel]**
> needs a professional before launch. Threshold figures change — verify current values.

The original draft had no compliance content. In a business that (a) pays thousands of
individuals small sums, (b) touches other companies' marketing assets, and (c) looks
superficially like a category payment processors shut down, that omission is the largest
single risk in the plan.

## 1. Existential risks, ranked

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| 1 | Payment processor classifies us as "paid to click" and terminates | **Medium-high** | Fatal | §4 |
| 2 | Panel never reaches liquidity; empty feed → churn spiral | **High** | Fatal | Concierge start, research-first wage, recruit against demand |
| 3 | A customer's AdSense / channel / domain is penalized by our traffic | Medium | Severe + reputational | §2, hard blocks |
| 4 | Fraud rings exceed 15% of panel spend | Medium | Severe margin | Trust score, device graph, ID verify at $50 lifetime |
| 5 | Trademark opposition on "Monster" in advertising services | Medium | Costly rebrand across portfolio | §7, clear before spending |
| 6 | Response quality too low; buyers don't repeat | Medium | Fatal to research thesis | Attention checks, guarantee, wage level |
| 7 | Data breach of panel PII | Low | Severe | Encryption at rest, RLS, least privilege, pen test pre-Phase 2 |

Risks 1 and 2 deserve most of your attention. They are the two that end the company, and
both are substantially mitigated by the same decision: **be a market research panel, not a
traffic exchange.**

## 2. Third-party platform policy

This is the section the draft most needed. The rule: **never sell a product whose failure
mode lands on the customer's asset rather than ours.**

**Google Ads / AdSense / Ad Manager.** Incentivized traffic is invalid traffic under
Google's publisher policies. A publisher receiving it can have their account terminated
and earnings clawed back. Controls: `runs_ad_network` question at signup; automated page
scan for ad-network markers at campaign approval; hard rejection of traffic campaigns to
monetized domains; explicit AUP clause; onboarding copy that tells customers *why*.

**Google Search.** Paid SERP clicking manipulates ranking signals and puts the client's
domain at risk of manual action. **Not offered.** Replaced by Search Result Testing, which
shows panelists a simulated SERP and asks which listing they'd choose — market research,
not manipulation.

**YouTube, TikTok, Instagram, Facebook, X.** Paid views are incentivized engagement under
these platforms' fake-engagement policies whether or not a like is requested. The strike
lands on the customer's channel. **Not offered as traffic.** Replaced by Creative Testing
against uploaded assets or public thumbnails/titles.

**Reddit.** Coordinated paid activity — including directed traffic to a thread — is
manipulation under sitewide rules. **Not offered.**

**Review platforms.** Google Business Profile, Yelp, Trustpilot, G2, Amazon, App Store,
Play Store go on a hard domain blocklist for every product. See §3.

**Analytics hygiene.** Even permitted traffic pollutes a customer's GA4 engagement metrics
and can degrade Google Ads Smart Bidding by feeding it non-converting sessions. The
PromoMonster tag sets a `pm_traffic` parameter so it can be segmented out, and onboarding
ships the GA4 filter instructions. Do this or you will lose customers who never tell you
why their numbers got worse.

## 3. Prohibited campaigns — must be in the AUP and enforced in code

Ad clicks · reviews or testimonials of any kind · social likes/follows/subs/upvotes/shares ·
search ranking manipulation · app installs for ranking · account creation · survey or form
completion under a false identity · anything on a site monetized by an ad network ·
malware, phishing, credential harvesting · adult content · gambling · crypto tokens/ICOs ·
MLM recruitment · payday and high-cost lending · counterfeit goods · anything unlawful.

**Fake reviews specifically.** The FTC's Rule on the Use of Consumer Reviews and
Testimonials (16 CFR Part 465, effective October 2024) carries civil penalties exceeding
$50,000 per violation and reaches parties who *facilitate* the buying of reviews — not just
the business that bought them. A single campaign that slips through review could generate
per-review penalty exposure. This is why review platforms are a hard blocklist and why
question text is scanned for review-solicitation language, rather than relying on a policy
document nobody reads. **[counsel]**

## 4. Payments — the top existential risk

**"Get paid to click" is a category Stripe and PayPal have shut down repeatedly.** GPT/PTC
sites lose their rails, without warning, holding customer funds and owing panelists. This
is a well-documented failure mode for this exact business model, and it is why the
positioning change in [01-strategy.md](01-strategy.md) is a *survival* decision and not a
marketing preference.

**Do:**
- Register and describe the business as **online market research / consumer insights**, because that is what the revenue mix will actually be
- Have the ToS, AUP and website consistent with that description before applying — underwriting reads the site
- Keep chargebacks under 0.65%; processors act near 1%
- Establish a **second processor before you need it** (Stripe primary, Braintree or Adyen secondary), and a second payout rail (PayPal primary, Tremendous secondary)
- Never let panelist funds sit in one provider's balance

**Do not:** use the phrase "get paid to click" anywhere public; let the earner-facing site
read like a PTC site; commingle panelist liability with operating cash.

**Money transmission. [counsel]** Paying your own contractors for services rendered is
generally not money transmission. But *holding* panelist balances resembles stored value.
Mitigations: ToS language framing balances as accrued unpaid compensation rather than
deposits; no panelist-to-panelist transfers; no spending earnings on credits; auto-payout
on a schedule rather than indefinite holding. Get an opinion before balances get large.

**Escheatment.** Unclaimed panelist balances become unclaimed property under state law
(typically 3–5 years dormant). Track dormancy and follow your state's reporting regime.
**[counsel]**

## 5. Worker classification & tax

**Classification. [counsel]** Panelists are independent contractors: they choose tasks,
set their own hours, use their own equipment, and there is no exclusivity or supervision.
That fact pattern is defensible and matches established research panels. Protect it: never
require minimum hours, never assign mandatory tasks, never impose schedules, and don't call
them employees or "staff" in any copy.

**1099 reporting.** Collect a W-9 before the first payout that crosses **$500 lifetime**
(deliberately below the filing threshold, so you're never chasing forms retroactively).
File 1099-NEC for panelists over the annual threshold. **The threshold moved from $600 to
$2,000 for payments made in 2026 and later under the 2025 tax act, with inflation indexing
after — confirm the current figure with your CPA.** Apply backup withholding where a TIN is
missing or fails matching. Non-US panelists: W-8BEN, no 1099. **[counsel]**

Practically: use a payout provider that handles W-9 collection, TIN matching and 1099
filing (Tremendous, Trolley, Tipalti). Doing this yourself at 3,000 panelists is a
part-time job you don't want.

**Sales tax.** SaaS subscriptions are taxable in 20+ US states. Enable **Stripe Tax from
day one** — retroactive sales-tax exposure is one of the standard ways small SaaS companies
get a nasty surprise at acquisition diligence. Whether credit packs are taxable services or
prepaid intangibles is a real question. **[counsel]**

## 6. Privacy & data

Device fingerprints, IP addresses and profile demographics are personal data under CCPA/CPRA
and GDPR.

- **Age 18+**, verified at signup by date of birth. Under-18 accounts are terminated and their data deleted.
- **Privacy policy** must disclose fingerprinting and IP collection for fraud prevention and name the legal basis.
- **CCPA/CPRA**: disclosure, deletion and opt-out rights; a "Do Not Sell or Share" link. The **Leads product is a disclosure to a third party** and needs explicit, granular, unbundled opt-in with a stored consent record (timestamp, IP, exact wording shown). **[counsel]**
- **Sensitive categories**: don't collect health, precise geolocation, biometrics, or protected-class data beyond what research targeting genuinely requires, and never as a targeting facet without counsel.
- **Right to delete vs. the ledger**: anonymize the user record, keep the financial rows with the reference nulled. Document this. Tax retention obligations override deletion requests for financial records — say so in the policy.
- **Open-text responses** may contain PII the panelist typed. Scrub before any aggregate export or published research.
- **Vendor DPAs** with every processor touching panel data.
- Encryption at rest for `payout_destination` and profile data; least-privilege DB roles; pen test before Phase 2.

## 7. Trademark **[counsel]**

"Monster" is aggressively enforced in and around advertising, promotion and media services
by well-funded owners with long opposition histories. PromoMonster in *advertising and
promotion services* sits closer to that zone than SearchMonster or MonsterList do, and an
opposition would reach your whole portfolio, not just this brand.

**Do this before spending on the brand:** a USPTO clearance search in the relevant classes
(35 advertising/marketing, 42 SaaS) plus a common-law search, and an attorney opinion.
Budget $2–4k. Confirm `.com` ownership and secure the handle set. Consider filing an
intent-to-use application early. A rebrand after you have customers and SEO equity costs
far more than the search.

## 8. Consumer-protection posture

- **Never guarantee** conversions, rankings, sales, leads, or platform outcomes. Sell delivered visits and delivered responses.
- Publish a **refund policy**: unused credits refundable within 30 days of purchase; unfilled study slots auto-released and refunded to balance.
- **Fill-rate SLA** stated up front: under 90% at 72 hours → automatic release and notification.
- Panelist terms must state plainly: rejection criteria, that fraud forfeits balances, that there is an appeal, and how disputes are handled.
- **Give a reason on every ban and allow one human appeal.** Panel businesses die on Trustpilot and Reddit, and silent bans with confiscated balances are the specific thing that kills them.

## 9. Pre-launch checklist

**Before the first dollar**
- [ ] Entity formed; EIN; business banking; panelist liability held separately
- [ ] Trademark clearance completed and reviewed
- [ ] ToS, AUP, Privacy Policy, Panelist Agreement drafted by counsel
- [ ] Stripe account approved under a market-research description; Stripe Tax on
- [ ] Payout provider selected with W-9/1099 handling built in
- [ ] Domain blocklist and ad-network detection implemented and tested
- [ ] 18+ gate; email verification mandatory; Turnstile on both signups
- [ ] Ledger invariant test in CI; nightly reconciliation job with alerting
- [ ] Incident runbook: processor suspension, data breach, fraud ring, panel liquidity failure

**Before scaling spend**
- [ ] Second processor and second payout rail live and tested
- [ ] Pen test complete
- [ ] Sales-tax nexus review
- [ ] Money-transmission opinion on file
- [ ] Escheatment tracking implemented
- [ ] SOC 2 readiness assessment (agency and mid-market buyers will ask)
