# 02 — Unit Economics

All figures USD and directional. **Verify SMS and 10DLC pricing with your chosen
provider before committing to a price list** — it is the one input that can
invert a tier's margin.

## 1. Headline

Ordinary SaaS margins, which the click model never had:

| | Click model | Reviews platform |
|---|---|---|
| Gross margin | ~55% | **~85%** |
| Cost of goods | Panelist payouts | SMS + Stripe |
| Marginal cost of a customer | High and permanent | Near zero on email-only tiers |

## 2. Cost of goods, per tier

Assumes a business contacting ~100 customers/month on Growth, ~300 on Pro.

| | Starter $39 | Growth $99 | Pro $199 |
|---|---|---|---|
| Stripe (2.9% + $0.30) | $1.43 | $3.17 | $6.07 |
| 10DLC campaign fee | — | $10.00 | $12.00 |
| SMS (2 msgs × ~$0.024) | — | $4.80 | $14.40 |
| Email | $0.05 | $0.15 | $0.40 |
| Infrastructure share | $0.50 | $1.00 | $2.00 |
| **Total COGS** | **$1.98** | **$19.12** | **$34.87** |
| **Gross margin** | **95%** | **81%** | **82%** |

A review request runs to two SMS segments once the link and the required opt-out
language are included, so budget per-message cost at roughly double the quoted
per-segment rate.

## 3. Blended

At a realistic mix — 20% Starter, 60% Growth, 20% Pro — blended ARPU is about
**$105** with roughly **84% gross margin**. Model **80%** to leave room for
support, refunds and the customers who send far more than the average.

## 4. The number that decides the pricing floor

**A2P 10DLC registration is charged per business, not per platform.** Every
customer who sends SMS needs their own brand and campaign registered, with a
recurring monthly campaign fee and one-off vetting costs.

Run it against the draft's $29 tier:

| | $29 tier with SMS |
|---|---|
| Revenue | $29.00 |
| Stripe | −$1.14 |
| 10DLC campaign | −$10.00 |
| SMS, 100 customers | −$4.80 |
| Infrastructure | −$0.50 |
| **Gross profit** | **$12.56 — 43%** |

43% before a single support ticket, on the tier that generates the most support
tickets. And that assumes the cheaper sole-proprietor registration path; on
standard brand registration the tier loses money.

**Two decisions follow:**

1. **Starter is email-only, at $39.** It stays at 95% margin and remains a real product — link, QR code, email requests, monitoring and the playbook are genuinely useful without SMS.
2. **SMS starts at Growth ($99)**, where a ~$15 all-in messaging cost is comfortably absorbed. This also makes the Starter→Growth upgrade self-evident, since SMS is what customers actually want.

**Watch multi-location.** A Pro customer with five locations may need five brand
registrations depending on how the entities are structured. Either cap
locations per brand or price locations separately — do not discover this after
signing a franchise.

## 5. Year-1 model

Solo founder, under $25k cash, Phase 0 concierge then self-serve. Assumes 5%
monthly churn — lower than the 8% used for the panel model, because reviews
accumulate on the customer's own Google profile and leaving means losing the
system that produced them.

| Mo | Customers | ARPU | Revenue | GP @84% |
|---|---|---|---|---|
| 1 | 4 | $75 | $300 | $252 |
| 2 | 9 | $78 | $702 | $590 |
| 3 | 16 | $80 | $1,280 | $1,075 |
| 4 | 26 | $82 | $2,132 | $1,791 |
| 5 | 38 | $85 | $3,230 | $2,713 |
| 6 | 54 | $88 | $4,752 | $3,992 |
| 7 | 74 | $90 | $6,660 | $5,594 |
| 8 | 98 | $92 | $9,016 | $7,573 |
| 9 | 126 | $94 | $11,844 | $9,949 |
| 10 | 158 | $96 | $15,168 | $12,741 |
| 11 | 194 | $98 | $19,012 | $15,970 |
| 12 | 235 | $100 | $23,500 | $19,740 |
| **Y1** | **235** | | **~$97,600** | **~$82,000** |

**Exit run-rate ~$23.5k MRR ≈ $282k ARR.**

### Cash costs

| Line | Amount |
|---|---|
| Legal — terms, privacy, DPA, TCPA and 10DLC review | $4,000 |
| Entity, banking, registered agent | $500 |
| Trademark clearance and opinion | $1,000 |
| Infrastructure, Stripe, email, tooling | $1,500 |
| SMS provider setup and testing | $500 |
| Content production for playbooks | $1,500 |
| Outreach tooling and small ad tests | $2,000 |
| Design and miscellaneous | $1,000 |
| Contingency | $4,000 |
| **Total** | **~$16,000** |

**Gross profit $82,000 − $16,000 = ~$66,000** before founder compensation.
Cash-positive, self-funding into year two, and roughly 60% better than the
research-panel version of this plan — almost entirely because gross margin is
84% instead of 55%.

Legal is the largest line and should stay that way. SMS compliance is where the
avoidable disasters live.

### Unit economics at month 12

- LTV = $100 × 0.84 ÷ 0.05 = **$1,680**
- CAC ≈ **$250** once founder time is costed at a notional $6,000/month
- **LTV:CAC ≈ 6.7x**, payback **~3 months**

Healthy, and healthier than the panel model at every point, because the margin
is structurally better and reviews retain.

## 6. The assumption most likely to be wrong

235 paying customers means roughly **310 gross adds** across the year, about
30/month by Q4, while also being the entire engineering and support team.

That is the number to watch. Two things make it achievable, and if adds stall
around 15/month both are where the time should go:

- **The free audit converting.** Showing an owner that a competitor has four times their reviews is the single strongest motivator in this market.
- **Playbook pages ranking.** Long-tail vertical queries are low-competition and high-intent, and each page ends in the audit.

## 7. Sensitivities

| If… | Then… | Response |
|---|---|---|
| 10DLC costs $25/mo per brand | Growth margin 81% → 66% | Raise Growth to $119, or meter SMS above an included allowance |
| Churn is 8%, not 5% | LTV $1,680 → $1,050 | Annual plans at two months free; the Growth Score is the retention mechanism |
| Google denies GBP API access | No monitoring or replies | Request-and-track still works on Place ID alone — ship that first regardless |
| A customer's SMS gets them a TCPA complaint | Legal exposure, carrier scrutiny | Consent capture and quiet hours enforced in code, not policy — see [05-compliance.md](05-compliance.md) §4 |
| Field-service platforms bundle harder | Home services closes off | Verticals they don't serve, and integrate rather than compete — [01-strategy.md](01-strategy.md) §6 |
