# 02 — Unit Economics & Financial Model

All figures USD. Panelist payouts are the primary cost of goods; treat them as COGS.

## 1. The governing rule

> **credits_required × floor_credit_price ≥ 2.4 × panelist_payout**

Floor credit price is **$0.070** (the deepest volume discount permitted, 30% off the $0.10
list price). Any task type that fails this rule cannot be listed. This single constraint is
what the original draft was missing, and it's why its pricing collapsed at volume.

**What went wrong in the draft:** the 5,000-credit pack at $349 puts a credit at $0.0698.
A basic visit was 1 credit paying the worker $0.05 — that's $0.0198 of gross margin, or
**28% before Stripe (2.9% + $0.30) and PayPal payout fees ($0.25/payout)**. Your largest
and most valuable customers would have been your least profitable. Meanwhile a feedback
response at 5 credits = **$0.35–0.44** against a market that pays $1.50–$3.00 for the
identical deliverable — underpriced by roughly 3–4x.

## 2. Credit price ladder

1 credit = **$0.10** list.

| Purchase | Price | $/credit | Discount |
|---|---|---|---|
| Pay-as-you-go (min $25) | — | $0.1000 | — |
| 500 pack | $47 | $0.0940 | 6% |
| 1,500 pack | $129 | $0.0860 | 14% |
| 4,000 pack | $319 | $0.0798 | 20% |
| 10,000 pack | $749 | $0.0749 | 25% |
| **Starter** | $49/mo | 600 cr → $0.0817 | 18% |
| **Growth** | $149/mo | 2,000 cr → $0.0745 | 26% |
| **Scale** | $399/mo | 5,500 cr → $0.0725 | 28% |

The discount curve flattens at the top on purpose. Discount the *unit* by at most 30%;
sell the top tiers on **seats, targeting, active-study limits, API, white-label reporting
and priority fielding** instead. Enterprise/annual commits can go below the floor only with
a signed contract and explicit margin approval.

Credits **do not expire** while the account is active or subscribed. Expiring credits on an
SMB is a chargeback generator and a review-site liability for the sake of a rounding error.

## 3. Task pricing table

Margin shown at the **floor** ($0.070/credit) — the worst case, not the average.

| Task type | Credits | Rev @ list | Rev @ floor | Panelist | GM @ floor | Ratio |
|---|---|---|---|---|---|---|
| Unverified visit | 1.5 | $0.15 | $0.105 | $0.04 | 62% | 2.6x |
| Verified visit (60s, tagged) | 2 | $0.20 | $0.140 | $0.05 | 64% | 2.8x |
| Targeted visit (geo/device) | 3 | $0.30 | $0.210 | $0.08 | 62% | 2.6x |
| Content read (2 min + check) | 4 | $0.40 | $0.280 | $0.11 | 61% | 2.5x |
| **Feedback response (3–6 Q)** | **12** | **$1.20** | **$0.840** | **$0.35** | **58%** | **2.4x** |
| Head-to-head / SERP / creative test | 15 | $1.50 | $1.050 | $0.42 | 60% | 2.5x |
| Long-form open response | 20 | $2.00 | $1.400 | $0.58 | 59% | 2.4x |
| Screened + profiled study | 30–60 | $3–6 | $2.10–4.20 | $0.85–1.60 | 60% | 2.5x |

Every row clears the 2.4x rule. Screener rejections are paid at $0.03 (panelists must be
paid for screen-outs or they stop attempting screened studies) and are billed to the
customer at 1 credit.

## 4. Panelist compensation — the number that decides whether this works

The draft's implied wage is the thing that would have killed it. A 60-second task at $0.05
is **$3.00/hour if tasks are always available** — and they won't be. Realized earnings at a
40% feed fill rate are under $1.50/hour. Nobody stays. The panel is the business, so the
wage is not a cost to minimize; it is the input that determines whether you have a company.

| Task | Time | Pay | Effective rate |
|---|---|---|---|
| Verified visit | 60s | $0.05 | $3.00/hr |
| Targeted visit | 75s | $0.08 | $3.84/hr |
| Content read | 150s | $0.11 | $2.64/hr |
| Feedback response | 90s | $0.35 | **$14.00/hr** |
| Head-to-head test | 100s | $0.42 | **$15.12/hr** |
| Long-form response | 180s | $0.58 | **$11.60/hr** |
| Profile completion (one-time) | 4 min | $0.50 | $7.50/hr |

Traffic tasks pay badly per hour and always will — the ceiling is set by what the
destination is worth. That is a second reason they can't be the main product. **Research
tasks pay $11–15/hour, which clears Prolific's recommended participant rate and is well
above MTurk's typical realized wage.** That is a genuinely attractive side income, and it
is what lets you recruit honestly on Reddit without getting torn apart.

Practical consequence for the product: **the task feed must prioritize research tasks.**
Traffic is the filler shown when research inventory is empty.

## 5. Gross profit per active panelist — the core comparison

Assume an engaged panelist works ~40 minutes/month.

**Traffic-only panel**
- 40 visits/month × $0.17 gross ($0.25 blended rev − $0.08 blended pay) = **$6.80 GP/panelist/mo**
- Panelist earns 40 × $0.08 = **$3.20/month** → churns

**Research-led panel**
- 30 responses/month × $1.15 gross ($1.50 blended rev − $0.35 pay) = **$34.50 GP/panelist/mo**
- Panelist earns 30 × $0.35 = **$10.50/month** at $14/hr → stays

**~5.1x the gross profit, ~3.3x the panelist income, from the same person.** And to reach
$60k/month revenue you need ~1,300 active research panelists versus ~6,000 traffic
panelists — a cold start you can actually execute.

## 6. Blended margin after real costs

Per $1,000 of customer spend, research-weighted mix:

| Line | Amount | Note |
|---|---|---|
| Revenue | $1,000 | |
| Stripe | −$32 | 2.9% + $0.30, ~$250 avg transaction |
| Panelist payouts | −$355 | |
| Payout rails | −$9 | $0.25/payout, ~$35 avg payout |
| Fraud/rejection re-fielding | −$28 | 8% of panel cost re-fielded |
| Free re-fields under quality guarantee | −$18 | capped at 20% of responses |
| **Gross profit** | **$558** | **55.8%** |

Budget **55% blended gross margin**, not the 50% the draft implied and not the 66% the
task table suggests. Rejection, re-fielding and the quality guarantee are real and must be
in the model from day one.

## 7. Year-1 bottom-up model

The operative model is the **bootstrap case** in
[00-execution-plan.md](00-execution-plan.md) §6: solo founder, under $25k cash, concierge
first, self-serve live in month 8.

**Summary:** ~$109,500 Year-1 revenue · ~$60,200 gross profit · $19,700 cash costs ·
**~$40,500 cash-positive** before founder compensation · exit run-rate ~$281k ARR ·
700–1,100 active panelists required at month 12.

Unit economics at month 12 (ARPU $170, 55% GM, 8% monthly churn):

- LTV = $170 × 0.55 ÷ 0.08 = **$1,169**
- CAC ≈ **$120** blended (outbound and content are founder time, not cash)
- **LTV:CAC ≈ 9.7x**, payback **~1.3 months**

Those ratios look implausibly good because the largest input — your labour — isn't costed.
Charge yourself a notional $6,000/month and CAC rises to roughly $400, LTV:CAC to ~2.9x and
payback to ~4.3 months. **That is the number to plan against**, and it's still healthy. It
is healthy because of the research mix: rerun it at traffic-only ARPU (~$60/mo) and payback
goes past 12 months, which is not a business.

### If funding later becomes available

A funded variant — contract developer, self-serve live in month 3 — reaches ~$215k Year-1
revenue and ~$594k exit ARR against ~$219k of costs, so roughly a **$101k loss** with
founder labour uncosted. It buys about five months of calendar time for around $140k of
additional burn. Worth considering only once Phase 0 has proven demand; before that it's
purchasing speed toward an unvalidated destination.

## 8. Metrics that decide whether this is working

**Leading, watch weekly**
- Median tasks available per panelist (target ≥ 5) — if this hits zero, the panel dies
- Study fill time, p50 and p95 (target: 100 responses in < 6 hours)
- Response rejection rate (target < 10%; > 20% means fraud or bad screening)
- Panelist D30 retention (target > 30%)
- Business 2nd-purchase rate within 30 days (target > 45%) — the real PMF signal

**Lagging, watch monthly**
- MRR, ARPU, logo and revenue churn
- Blended gross margin (alarm below 50%)
- CAC by channel, LTV:CAC, payback months
- Fraud loss as % of panel spend (target < 5%)
- Refund + chargeback rate (alarm above 2% — processors act at 1%)

## 9. Sensitivities worth pre-computing

| If… | Then… | Mitigation |
|---|---|---|
| Panel wage must rise to $0.50/response | GM 58% → 40% | Raise to 15 credits; lean on targeted studies |
| Churn is 12% not 8% | LTV $1,031 → $688, LTV:CAC 2.8x | Annual plans; agency accounts churn less |
| CAC is $400 not $250 | Payback 4.8 months | Ecosystem distribution is the lever — it's ~free |
| Fraud loss hits 15% | GM −6 pts | Trust score gating; ID verification above $50 lifetime |
| Stripe classifies you as high-risk | Rails lost | See [05-risk-compliance.md](05-risk-compliance.md) §4 — this is the top existential risk |
