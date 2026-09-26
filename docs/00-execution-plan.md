# 00 — Execution Plan

Solo founder, under $25,000 of Year-1 cash. The other documents describe the
destination; this is what you do on Monday.

## 1. Budget

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
| Contingency — do not pre-spend | $4,000 |
| **Total** | **~$16,000** |

Legal is the largest line and should stay that way. SMS is where the avoidable
disasters are.

## 2. Start these on day one, because they gate everything

Two dependencies have lead times measured in weeks. Neither is work you can
compress later.

1. **Apply for Google Business Profile API access.** Review monitoring and reply features do not exist without it, approval takes time, and it can be refused. Apply before you write any code.
2. **Trademark clearance on "PromoMonster"** in the relevant classes. "Monster" marks in advertising and marketing services draw opposition from well-funded enforcers, and you have SearchMonster and MonsterList exposed alongside it. Clear it before spending on the brand.

Start the **SMS provider account and 10DLC onboarding** in week 2. You need to
know real registration costs before you can price Growth honestly.

## 3. Phase 0 — weeks 1–8. Sell before building.

Almost no code. The goal is 20 paying customers and a specification written from
what they actually asked for.

| Need | Tool | Cost |
|---|---|---|
| Landing + playbook pages | The existing PHP site | already built |
| Free Review Audit | **You run it by hand**, deliver a PDF | $0 |
| Toolkit and DFY sales | Stripe Payment Links | 2.9% + 30¢ |
| Customer tracking | A spreadsheet | $0 |
| Request sending | The customer's own phone, following the playbook | $0 |

**Week by week**

- **1–2** — Entity, banking, domain. GBP API application in. Trademark search started. Rewrite the site for reviews (the shell already exists).
- **3–4** — Write three playbooks in full: landscaping, HVAC, one non-home-services vertical. Publish as pages. Build the audit as a repeatable manual process, then run 20 for free on local businesses and send them unsolicited. This is the best cold outreach available in this market.
- **5–6** — Sell. The audit *is* the pitch: they can see the gap. Offer the $29 toolkit and $299 done-for-you setup. Target the first 10 paying customers.
- **7–8** — Deliver DFY setups by hand and watch where the time goes. That tells you exactly what to automate first. Ten more customers. Write down every question you could not answer.

**Gate: do not start Phase 1 until 20 customers have paid and at least 5 have
bought done-for-you.** If the audit does not convert when you deliver it
personally, software will not fix it.

## 4. Phase 1 — months 2–6. The core loop.

Roughly 300–400 hours. At 20 hours a week that is 15–20 weeks, while also
selling and supporting.

**Build exactly this:**

1. Auth and accounts
2. Google Place connection, review link generation, QR code generation
3. Contact import (CSV, paste)
4. **Email** requests and one follow-up
5. Request tracking — sent, opened, clicked, review detected
6. Review monitoring and the Growth Score
7. The automated Review Audit (this is the acquisition engine — build it properly)
8. Admin: accounts, sends, deliverability, abuse

**Deliberately not in Phase 1:** SMS (it needs the whole 10DLC onboarding flow,
which is a project of its own), the website widget, reply drafting,
multi-location, team accounts, the partner programme.

**Buy, don't build:** auth, email delivery, QR generation, PDF rendering. Write
none of these.

## 5. Phase 2 — months 6–10

SMS with 10DLC onboarding, consent capture, STOP/HELP, quiet hours and
suppression — all of it, before a single message sends. Then follow-up
sequences, the website widget, reply drafting, multi-location, and the partner
programme.

## 6. What must be true in the code from day one

These are cheap now and near-impossible to retrofit:

- **Consent records** on every contact: timestamp, source, exact wording shown.
- **A global suppression list** that survives account deletion.
- **No gating, anywhere** — no branching on predicted sentiment, and no code path that sends different people to different destinations based on how they might feel. See [05-compliance.md](05-compliance.md) §1.
- **Incentive-language scanning** on any message a customer edits.
- **Per-step instrumentation** — trigger, channel, sender, timing, outcome. This is the dataset that becomes the moat ([06-playbooks.md](06-playbooks.md) §11).
- **Contact data encrypted at rest**, and never reused for anything.

## 7. Kill criteria

Decide now, while it is cheap:

- **Week 8** — fewer than 10 paying customers after 20 hand-delivered audits. The offer is not landing; fix that before building.
- **Month 6** — 10DLC economics make Growth unviable at $99 and customers won't pay $119+. Reprice or drop SMS to a metered add-on.
- **Month 8** — churn above 8%/month. Reviews should retain; if they don't, the product is not delivering results.
- **Any time** — a TCPA complaint or carrier action traced to the platform. Stop sending, investigate fully, and fix the root cause before resuming.

## 8. First five things

1. Google Business Profile API application submitted.
2. USPTO knockout search on "PromoMonster" in classes 35 and 42.
3. LLC formed, bank account open.
4. Three playbooks written and published.
5. Twenty free audits run by hand and sent to local businesses.

Step 5 is the one that matters. Everything before it is setup.
