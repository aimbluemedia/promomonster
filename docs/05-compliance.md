# 05 — Compliance

> **Not legal advice.** Items marked **[counsel]** need a professional before
> launch. Rules and dollar figures change — verify current text.

This document comes first in importance, because in this category the thing that
kills you is not competition. It is building the feature every competitor
started with and had to rip out.

## 1. Review gating — do not build it

**Review gating** is surveying a customer first ("how did we do?"), then sending
only the happy ones to Google and routing unhappy ones to a private feedback
form.

It is the single most requested feature in review software. It is also
prohibited, twice over:

- **Google's review policies** prohibit selectively soliciting positive reviews and discouraging or preventing negative ones. Businesses caught gating have had reviews removed and profiles actioned.
- **The FTC's Rule on Consumer Reviews and Testimonials** (16 CFR Part 465, effective October 2024) treats suppressing negative reviews as deceptive, with civil penalties exceeding **$50,000 per violation** — and it reaches parties who *facilitate* the conduct, not only the business doing it. **[counsel]**

Every major competitor built gating and most removed it after Google's
enforcement pushed back. Building it in 2026 would be knowingly shipping a
feature that endangers your customers' Google profiles.

**What to build instead:** ask **every** customer, in the same way, with the
same message. Then use the private feedback that arrives *alongside* reviews to
help the business fix operations. That is legal, it is more useful, and it is a
sharper marketing story than gating ever was:

> *"We ask all your customers, not just the ones you think will say something
> nice. That's the only way it's allowed — and it's the only way the score means
> anything."*

Make it a visible product principle. Prospects will ask whether you gate,
because your competitors trained them to.

## 2. Incentives — do not teach them

Google prohibits offering **any** incentive for a review. Not a discount, not a
prize draw, not a free coffee, not "leave a review and we'll enter you in a
drawing." Sentiment doesn't matter; the incentive itself is the violation.

The FTC rule is narrower — it targets compensation **conditioned on sentiment** —
but Google's policy is what determines whether your customer's reviews survive,
and it is stricter. Follow Google's line.

Enforce this in the product, not just the terms:

- Scan customer-authored message templates for incentive language (`discount`, `% off`, `free`, `gift card`, `entry`, `raffle`, `voucher`, `coupon`) and block the send with an explanation.
- Ship compliant templates by default so the easy path is the correct one.
- Say plainly in onboarding why this rule exists.

## 3. Which platforms you may ask for

| Platform | Soliciting reviews | Build it? |
|---|---|---|
| **Google Business Profile** | Permitted, if asked of everyone and unincentivised | **Yes — the core** |
| **Facebook** | Permitted | Phase 2 |
| **Trustpilot** | Permitted, with rules on invitation wording | Phase 2 |
| **Yelp** | **Explicitly prohibited.** Yelp's policy forbids asking for reviews at all, and its filter demotes solicited ones | **Never** |
| **BBB, industry sites** | Varies | Case by case |

**Yelp needs a hard block**, not a footnote. Business owners will ask for it
constantly. Telling them why — and that a competitor who offers it is
endangering their Yelp page — is a trust-building moment, so write that
explanation once and reuse it.

## 4. SMS is the hard part, and it is not optional to get right

Text gets far better response than email for review requests, which is why the
category runs on it. It is also where the real legal and operational cost lives,
and the original plan didn't mention it.

### A2P 10DLC registration is mandatory

Since carrier enforcement tightened, application-to-person SMS on US networks
requires registration with The Campaign Registry: a **brand** registration per
business, and a **campaign** registration per use case. Unregistered traffic is
filtered or blocked outright — not delivered-but-flagged, *blocked*.

Consequences for the build:

- **Every customer needs their own brand registration.** You cannot send on your own brand on their behalf; that is exactly what carriers are filtering for.
- There are one-off vetting fees and recurring per-campaign fees, plus per-segment carrier surcharges on top of your provider's price. **This is the main threat to a $29/month tier** — see [02-unit-economics.md](02-unit-economics.md) §4.
- Registration takes days and can be rejected, so onboarding is not instant. Design for a "pending carrier approval" state rather than pretending sending works on day one.
- Sole-proprietor registration exists for very small businesses at lower throughput. Most of your ICP will be sole props or single-location LLCs, so verify the current path and cost before pricing. **[counsel]**

### TCPA **[counsel]**

Whether a review request text is "marketing" is genuinely contested. It goes to
an existing customer about a completed transaction, which argues informational —
but it also promotes the business, which argues marketing, and courts have not
settled it. Plaintiff firms are active in this space.

The defensible posture:

- Collect and store **prior express written consent** at the point the business collects the customer's number, and require the business to confirm they have it before any send.
- Store the consent record: timestamp, source, exact wording shown.
- Honour STOP, UNSUBSCRIBE, CANCEL, END, QUIT and HELP automatically and permanently, across the whole platform.
- Respect quiet hours (before 8am / after 9pm in the **recipient's** timezone, and follow the stricter state rules — Florida, Oklahoma and Maryland each have their own).
- One request plus at most one follow-up. Never a third.
- Keep a suppression list that survives account deletion.

State mini-TCPA statutes are stricter than federal in several states and carry
private rights of action. Do not assume federal compliance is sufficient.

### Email

CAN-SPAM is comparatively simple: accurate headers, physical address,
functioning unsubscribe honoured within 10 business days. Use a reputable ESP
and authenticate with SPF, DKIM and DMARC — Google and Yahoo require them for
bulk senders, and a review-request domain that fails alignment lands in spam.

## 5. Google API dependencies — start these early

**There is no API that posts a review.** You can only deep-link a customer to
the review form:

```
https://search.google.com/local/writereview?placeid=<PLACE_ID>
```

Place IDs come from the Places API.

**Reading and replying to reviews** needs the Google Business Profile API, and
that has a gate: the business must OAuth-connect their profile, **and you must
apply to Google for API access**. Approval takes time and can be refused.

Consequences:

- Apply the week you start. It is the longest-lead dependency in the build, and review monitoring and reply features are dead without it.
- Have a fallback: request-and-track works with only a Place ID, so the product must be useful before API access lands.
- Respect the API's caching and display terms. **[counsel]**

### 5a. "Can we post reviews to Google through an API?"

No. Not with permission, not with the customer's blessing, not through any
partner tier. It will be asked repeatedly — by you, by customers, by agency
partners — so here is the answer once, in full.

**No such API exists, and that is deliberate.** Google's review corpus is only
worth anything because each review is tied to a real Google account that
personally wrote it. An endpoint that let software post on someone's behalf
would destroy that in a week, so it has never existed and will not.

What the Google Business Profile API can do is **read** reviews and **post
replies as the business owner**. That is the whole surface. There is no create,
no import, no migrate, no bulk upload.

**What the members area can legitimately do**

| Want | Mechanism |
|---|---|
| Get a customer to Google's review box in one tap | Deep link `https://search.google.com/local/writereview?placeid=<PLACE_ID>` |
| Look up the Place ID | Places API |
| Know a review arrived, and which request produced it | GBP API polling, matched against `review_requests.sent_at` |
| Reply to a review from our UI | GBP API reply endpoint, as the owner |
| Show reviews on the customer's own site | GBP API read + our widget |

The deep link is the entire posting mechanism. Everything else is measurement
and response around it. That is not a limitation to engineer around — it is the
product.

**The grey pattern, assessed honestly.** Some tools capture the review text in
their own UI, copy it to the clipboard, then open Google so the customer pastes
it. Where the customer wrote every word themselves and posts it under their own
account, that is defensible and several established tools do it. Two things
make it indefensible, and the line is sharp:

- **The product drafting or suggesting the text.** Then the business is
  authoring reviews and the customer is a signature. That is a fabricated
  review under the FTC rule regardless of who clicked post.
- **Templated output at volume.** A hundred reviews with the same shape is
  exactly the pattern Google's filters look for, and the removals land on the
  customer's profile.

Recommendation: don't build it. The extra conversion is small, the failure mode
lands on the customer, and it undercuts the one claim the whole brand rests on.
If it is ever built, the customer must type their own words with no
suggestions, no starter text and no AI assistance whatsoever.

**Other platforms differ.** Trustpilot has a genuine invitation API, and some
industry platforms do too. Google and Yelp do not. Do not let a customer's
experience of Trustpilot set their expectation of Google.

**The API worth building is your own.** A members-area API that creates
contacts, triggers requests, and reports review status is genuinely useful to
agencies and to customers with a CRM — and it is on the roadmap. It moves data
into PromoMonster and pulls results out. It never posts to Google, because
nothing can.

## 6. Handling what customers write

- **Private feedback is not a review.** Never republish it as one, and never display it publicly without explicit permission. That would be manufacturing testimonials.
- **Website review widgets** must show real, attributed reviews pulled from the source, never edited and never cherry-picked in a way that misrepresents the overall rating. Selective display is the same deception as gating, wearing different clothes.
- **AI-drafted replies** are fine; AI-drafted *reviews* are fraud. If you ship reply drafting, the product must make it obvious a human sends it, and it must never generate review text.

## 7. Data protection

Businesses will upload customer names, phone numbers and email addresses — data
about people who never heard of you.

- You are a processor acting for the business. Put a **data processing agreement** in the terms. **[counsel]**
- Encrypt contact data at rest; restrict which staff can read it.
- Deletion: when a business leaves, delete their customer list on a stated schedule and say so in the contract.
- CCPA/CPRA applies to their customers' data. Support access and deletion requests reaching you through the business.
- Never reuse one customer's contact list for anything else. Not for your own marketing, not for aggregate products, not ever. This is the fastest way to destroy the trust the whole business runs on.

## 8. The "business in a box" offer needs rethinking

Selling a $499–$999 "start your own review management business" package is the
riskiest item in the plan, for three reasons:

1. **Processor risk.** Business-opportunity offers sit next to make-money-online in every underwriting model. High refund and chargeback rates in that category can cost you the payment rails for the *whole* company — the same failure mode identified for the click model.
2. **FTC Business Opportunity Rule.** Selling a packaged business opportunity with earnings representations triggers disclosure obligations. The "$1,990/month at 10 clients" maths in the draft is an earnings claim and would need substantiation and a disclosure document. **[counsel]**
3. **It poisons your sending reputation.** Buyers of these packages are, on average, less careful than agencies. They will import scraped lists, text people who never consented, and trigger carrier filtering and TCPA complaints — against *your* platform. One bad operator can degrade deliverability for every legitimate customer you have.

**Recommended instead:** a vetted **partner programme** — application, a short
certification, revenue share, and the right to remove anyone who abuses it. Same
distribution, none of the three problems, and it keeps control of who sends
through your infrastructure.

## 9. Pre-launch checklist

- [ ] Entity, business banking, terms, privacy policy, DPA drafted by counsel
- [ ] Google Business Profile API access applied for
- [ ] SMS provider chosen; 10DLC registration flow built and costed
- [ ] STOP/HELP handling, quiet hours and suppression list implemented and tested
- [ ] Consent capture and consent records in the schema from day one
- [ ] Incentive-language scanning on customer-authored templates
- [ ] Yelp hard-blocked with an in-product explanation
- [ ] No gating anywhere, and the reason stated publicly
- [ ] SPF, DKIM, DMARC aligned on the sending domain
