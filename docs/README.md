# PromoMonster — Business Plan & Build Package

> **Get More Reviews. Get More Customers.**
> PromoMonster shows local businesses exactly how to turn happy customers into
> Google reviews — then automates the process.

Read in order.

| Doc | Covers |
|---|---|
| **[00-execution-plan.md](00-execution-plan.md)** | **Start here.** Budget, week-by-week, kill criteria |
| [01-strategy.md](01-strategy.md) | Positioning, offer ladder, competition, go to market |
| [02-unit-economics.md](02-unit-economics.md) | Margins, SMS and 10DLC costs, Year-1 model |
| [05-compliance.md](05-compliance.md) | Review gating, FTC, Google policy, TCPA, 10DLC |
| [06-playbooks.md](06-playbooks.md) | The vertical playbooks — the product's differentiator |

**Operating constraints:** solo founder, under $25,000 of Year-1 cash.

Product spec and data model for the reviews platform are still to be written;
[03](archive/03-product-spec.md) and [04](archive/04-data-model.md) in the
archive remain useful as patterns — the ledger, leasing and fraud approaches
carry over — but the entities do not.

---

## The pivot

PromoMonster was previously planned as paid human traffic, then as a consumer
research panel. It is now a **review generation and reputation platform**. The
earlier documents are in [`archive/`](archive/) — the strategy is superseded,
but [`archive/06-platform-enforcement.md`](archive/06-platform-enforcement.md)
is still directly relevant, since it covers the FTC reviews rule and why buying
reviews is a business-ending mistake.

This is a better business on every axis: it is legitimate rather than
policy-violating, it is ordinary SaaS to payment processors, it can be sold on
day one with no marketplace to bootstrap, and gross margin is ~84% rather than
~55%.

## What changed from the pivot brief, and why

The brief was strong. Four changes:

### 1. Never build review gating — and say so publicly

Gating (surveying first, sending only happy customers to Google) is the most
requested feature in this category and it is prohibited by **both** Google's
review policies and the FTC's 2024 reviews rule, which carries penalties over
$50,000 per violation and reaches parties who *facilitate* it.

Every major competitor built it and most had to remove it. Do not build it —
and make "we ask all your customers, not just the ones you think will say
something nice" a stated product principle, because prospects will ask.
[05-compliance.md](05-compliance.md) §1.

### 2. SMS is the hard part, and the brief didn't mention it

Review request texts require **A2P 10DLC registration per business**, with
recurring per-campaign fees. Unregistered traffic is blocked outright, not
merely flagged. Plus TCPA consent, STOP/HELP handling, quiet hours by recipient
timezone, and state statutes stricter than federal.

This is the single largest build item and the main threat to a cheap tier —
which drives the next change.

### 3. The $29 tier is repriced to $39 and made email-only

At $29 with SMS, 10DLC fees alone take a third of the revenue:

| $29 tier with SMS | |
|---|---|
| Revenue | $29.00 |
| Stripe | −$1.14 |
| 10DLC campaign | −$10.00 |
| SMS, 100 customers | −$4.80 |
| Infrastructure | −$0.50 |
| **Gross profit** | **$12.56 — 43%** |

43% on the tier that generates the most support, assuming the *cheaper*
registration path. Starter becomes **$39, email-only, 95% margin**; SMS starts
at Growth where it pays for itself — which also makes the upgrade obvious.

### 4. "Business in a box" becomes a vetted partner programme

A $499–$999 "start your own review business" package carries three problems: it
sits in the business-opportunity category that costs companies their payment
processing, the "$1,990/month at 10 clients" maths is an earnings claim under
the FTC Business Opportunity Rule, and — most damaging — buyers of these
packages import scraped lists and text people who never consented, destroying
the sending reputation of every legitimate customer on the platform.

A vetted partner programme gives the same distribution with none of it.

## What was kept

Nearly everything else. Education-first funnel, the free audit as the
conversion mechanism, vertical playbooks, the Review Growth Score and Next Best
Action, the three audiences, the agency channel, the content strategy, and the
offer ladder shape. Those were the right calls.

Two additions worth flagging: the **free audit run by hand** in Phase 0 is the
strongest cold outreach available in this market, and **instrumenting every
playbook step** turns the content from your best guess into measured fact that
no competitor can copy.
