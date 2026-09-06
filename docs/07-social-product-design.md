# 07 — Social Testing: How to Show Panelists Social Content Legitimately

Companion to [06-platform-enforcement.md](06-platform-enforcement.md). That document says
what not to sell. This one says what to build instead, and why each mechanism is clean.

## 1. The principle

The violation is narrow and specific:

> **A compensated action that inflates a platform's own measured metrics.**

Three parts, all required. Compensated. Action on the platform. Metric moves. Break any one
and the problem goes away.

Almost every clean mechanism below works by breaking part two or three: the panelist sees
the content, but the platform never registers a view, because the platform is never touched.

That's the whole trick, and it's not a loophole — it's how the entire creative-testing
industry already works. PickFu, Wynter, Zappi and every agency pre-test does exactly this.
Nobody uploads an ad to TikTok to find out if the ad is good.

## 2. Mechanism 1 — Creative upload + simulated feed *(safest, and the best product)*

The business uploads the actual asset to PromoMonster: thumbnail image, video file, caption,
first 15 seconds, carousel frames. Panelists view it in **our** player, on **our** page.

The platform is never contacted. No view, no impression, no engagement, nothing to detect,
nothing to penalize. This is 100% clean and needs no hedging.

**Render it in a realistic feed frame.** A thumbnail evaluated on a white background tells
you very little; the same thumbnail at feed scale, with the caption truncating at two lines
and a play button covering the subject's face, tells you a lot. Build three frames:

- **Vertical feed** — full-bleed 9:16, caption overlay bottom-left, action icons right rail, safe areas marked
- **Video results row** — 16:9 thumbnail, title truncating at two lines, channel name, duration badge
- **Image + caption post** — square/4:5 image, caption truncating with "more"

**Mimic layout, not identity.** Copy the *geometry* — safe areas, truncation points, icon
positions, overlay placement — because that's what changes the answer. Do not reproduce
platform logos, exact iconography or brand colours, and label the frames generically in the
UI ("Vertical video preview", not "TikTok preview"). Naming a platform in *body text* to
describe what's being tested is ordinary nominative reference and fine; dressing your
product up as their interface is a trade dress question you don't need. **[counsel]**

### The two tests worth building on top of this

**Thumb-stop test.** Drop the asset into a mock feed with four pieces of neutral filler
content and let the panelist scroll naturally. Measure whether they stop, and for how long.
This measures the only thing that matters in a feed and cannot be faked by a panelist trying
to finish quickly.

**Recall test.** Show the creative for a fixed 3-second exposure — real scroll speed — then
hide it and ask what they remember: what was it for, what was the offer, whose brand was it.
This is how professional ad recall testing works. It produces devastatingly useful answers
("41 of 100 could not name the product") and is completely immune to the low-effort
responses that plague forced-dwell tasks.

Neither of these is possible with a paid view. **The clean version is a strictly better
product than the thing it replaces** — that's the argument to lead with commercially, not
the compliance one.

## 3. Mechanism 2 — Metadata card for already-published content *(safe, with API caveats)*

When the content is already live and the customer wants it evaluated as it actually appears
in discovery, fetch its **public metadata server-side** — thumbnail, title, channel, duration,
view count — and render a static card in our UI.

**The requirement that makes it safe: fetch and cache server-side.** The panelist's browser
must never request anything from the platform's servers or CDN. Proxy the thumbnail through
our own storage. If the browser loads platform assets directly you're generating request
traffic from a coordinated paid population, which is the pattern you're trying to avoid.

No player is loaded, so no view is counted, because a view requires playback initiation.

**Caveats to check before building this. [counsel]**
- Platform API terms typically restrict how long metadata and thumbnails may be cached, and prohibit using the data to build a substitute viewing experience. Read the current terms for each API you use.
- Instagram/Facebook oEmbed requires an app and token; access has been tightened repeatedly.
- Prefer official APIs and oEmbed over scraping. Scraping public pages at volume is its own ToS problem and a fragile dependency.
- Where the customer owns the content, **just have them upload it** (mechanism 1) and skip all of this. Reserve metadata cards for competitor content.

## 4. Mechanism 3 — Competitive set testing *(safe, high value, hard to copy)*

Show four cards side by side: the customer's post and three real competitors'. Ask which
they'd click, and why.

This is the highest-value social product and the one nobody can undercut, because it needs
a panel. It's also the same engine as the Search Result Test — one implementation, two
products.

**Copyright note. [counsel]** The customer's own creative is theirs. Competitors' creative
displayed for comparative research and commentary is a much better fair-use posture than
republication, and it's what every competitive-analysis tool does — but it isn't zero. Use
thumbnails at display resolution, never full videos; don't retain competitor assets longer
than the study; don't let customers export competitor creative from your reports.

## 5. Mechanism 4 — Awareness tracking *(zero platform contact, recurring revenue)*

Don't show content at all. Ask a fresh sample of 200 panelists the same five questions every
month:

- Unaided recall: *"Name any pool service companies in the Phoenix area."*
- Aided awareness: *"Which of these have you heard of?"*
- Consideration: *"Which would you contact first?"*
- Association: *"What comes to mind when you hear [brand]?"*

Then chart the trend. Unaided recall in-metro going 4% → 7% over six months is a real
result that a business will pay for monthly, forever.

This is a scaled-down version of what Nielsen and Kantar sell as brand lift, and it is the
**natural home for the subscription tier** the plan wants. Studies are inherently bursty;
tracking is inherently recurring. It touches no platform, has no compliance surface at all,
and the panel cost is trivial (200 responses at $0.35 = $70 against a $199/month plan).

I'd rank this the most underrated product in the whole plan.

## 6. Mechanism 5 — Redirect to an owned destination *(clean, subject to the AdSense rule)*

If what the customer actually wants is traffic rather than insight, send it to **their own
site**, never to the social post. Their property, their metrics, their call. Still subject
to the ad-network block in [05-risk-compliance.md](05-risk-compliance.md) §2, and still
requires the PromoMonster tag for verification.

Most "promote my YouTube video" requests are really "get more people to know about my
business." Route them here.

## 7. The grey one, stated honestly

**Uncompensated voluntary click-through.** Panelist evaluates the creative card, answers
"would you click this?", and if yes is offered an *unpaid, optional* link to the live post.
The survey was paid; the click was not.

**The argument for:** the visit itself is uncompensated and genuinely voluntary, which is
the ordinary meaning of organic.

**The argument against, which I find more persuasive:** the traffic still originates from a
coordinated paid population and arrives as a burst from one referrer. That aggregate pattern
is what detection looks at, and "we paid for the survey, not the click" is precisely the
structuring that reads as deliberate evasion if anyone examines it.

**My recommendation: don't ship it in year one.** If you do later: no compensation of any
kind tied to the click, hard volume caps, no referrer, never sold as a traffic product,
never guaranteed, and not offered to customers whose destination is monetized. And be aware
that offering it at all weakens the market-research positioning that keeps your payment
processor — which is a real cost, paid by the profitable part of the business.

## 8. Two things that don't work, despite sounding like they should

**`youtube-nocookie.com` embeds.** Privacy-enhanced mode changes cookie behaviour, not view
counting. A user-initiated play in a nocookie embed is still a view. Embedding the live
player in any form and asking someone to watch is a paid view with extra steps.

**"We only asked them to watch, not to like."** The Fake Engagement policy is about *why*
the engagement happened, not which button was pressed. A view that occurred because someone
was paid to produce it is the thing the policy describes. This distinction feels meaningful
and is worth nothing.

## 9. What to build, in order

| Order | Mechanism | Compliance | Build cost | Product value |
|---|---|---|---|---|
| 1 | Creative upload + feed frames | Clean | Low — an upload field and three CSS frames | High |
| 2 | Competitive set testing | Clean¹ | Low — same engine as SERP test | Highest |
| 3 | Awareness tracking | Clean | Low — recurring study scheduler | High + recurring |
| 4 | Recall / thumb-stop tests | Clean | Medium — timed exposure, mock scroll | Highest |
| 5 | Metadata cards | Safe² | Medium — API integration, caching | Medium |
| 6 | Owned-destination redirect | Clean³ | Already in the traffic roadmap | Low |
| 7 | Voluntary click-through | Grey | Low | Low |

¹ copyright posture on competitor creative — §4 · ² platform API terms — §3 · ³ ad-network block still applies

**Items 1 and 2 are roughly two days of work each** on top of the study engine already
specified in [03-product-spec.md](03-product-spec.md) — an upload field, three CSS frames,
and a multi-asset variant of the existing question flow. They belong in Phase 1, not
year two.

That is the answer to "how do we let people view social posts without hurting the terms":
**you don't show them the post — you show them the content, and you keep the platform out
of it entirely.** The customer gets a better answer, you get 10x the revenue per response,
and there is nothing for anyone to enforce against.

---

## 10. The credit-exchange / engagement-pod variant — evaluated and rejected

**The proposal:** don't pay cash for views. Give members credits. They earn credits by
viewing others' social content and spend credits to have their own content viewed. A
community that shares each other's posts.

**Verdict: this is worse than paying cash, on every axis.** Recording the reasoning here so
it doesn't get relitigated.

### 10.1 The dilemma that has no third option

Either credits can be bought with money, or they can't.

- **If they can be bought** → cash buys credits, credits buy views. You have sold paid views with one extra step. Platforms and regulators both read through form to substance; the extra step changes nothing about what happened.
- **If they can't be bought** → no money enters the system and there is no revenue.

There is no configuration that is both compliant and a business. That alone settles it, but
the rest matters too.

### 10.2 Credits are compensation

Platform policies do not say "paid with money." They describe artificial, inauthentic or
incentivized engagement. A credit redeemable for views on your own content has value — that
is barter, and barter is compensation.

More pointedly, **exchange schemes are named explicitly** in several of these policies, not
merely implied. TikTok's fake-engagement guidelines reach the trading of engagement. Meta's
inauthentic-behavior standards cover coordinated efforts to inflate engagement, and
Instagram has acted against engagement pods specifically and repeatedly. YouTube's
fake-engagement policy reaches views obtained through incentives or exchange.

A credit pod isn't an untested grey area. It's a named, well-known abuse pattern with a
decade of enforcement history behind it. *(Verify current policy text before any final
decision — but the substance here has been stable for years.)*

### 10.3 It is dramatically **more** detectable, not less

This is the counterintuitive part and the one that decides it.

Cash-paid views produce **one-directional** traffic: viewers who never appear again and have
no relationship to each other. Bad, but diffuse.

A credit exchange produces **reciprocal closed loops**: A views B, B views C, C views A,
repeatedly, among the same bounded population. That is a densely clustered, bidirectional
engagement graph — the single most recognizable inauthentic pattern in existence, and the
exact thing a decade of pod-detection tooling was built to find.

And it gets worse: every participant must be **logged into their real account** on the
platform, because they need somewhere for their own content to live. So the platform sees
the entire ring, fully mapped, attached to real identities. Cash viewers are at least
pseudonymous. Pod members are not.

### 10.4 The blast radius multiplies

Paid traffic risks **one customer's** asset per campaign. A credit pod risks **every
member's account simultaneously**, and every member is your user.

A single platform sweep could remove or restrict thousands of your members' accounts in one
action, all traceable to your product. That is not a support ticket — it's a brand-ending
event, and it would be entirely deserved.

### 10.5 It destroys the research panel, which is the actual business

The quietest problem and possibly the most expensive.

A credit exchange self-selects for **people who want promotion** — creators, marketers,
affiliates, small-business owners pushing their own content. That is the opposite of the
population your research customers pay for.

The whole value of [01-strategy.md](01-strategy.md)'s core product is that respondents are
**ordinary consumers** giving honest first impressions. A panel of marketers evaluating a
landing page produces systematically unrepresentative answers: they notice funnel mechanics
a real customer never sees, and they miss the confusion a real customer actually feels.

So the credit model doesn't merely fail to make money. It contaminates the asset that does.

### 10.6 Barter has its own tax exposure **[counsel]**

Formal barter exchanges carry specific IRS reporting obligations — broadly, Form 1099-B
filing for members' barter transactions, distinct from the 1099-NEC handling already
planned in [05-risk-compliance.md](05-risk-compliance.md) §5. Running a credit exchange may
pull you into a reporting regime you have not scoped, on top of everything above. Worth
knowing before anyone finds it attractive again.

### 10.7 The legitimate versions of the same instinct

The instinct — *a community where people discover each other's content* — is good. Three
ways to have it cleanly:

**A. Creator feedback exchange — recommended, and genuinely promising.**
Members upload their thumbnail, title or creative **to PromoMonster**. They earn credits by
giving thoughtful critiques of other members' creative, and spend credits to get critiques
of their own. **No platform is ever touched.** Nobody views anything on YouTube; nobody's
metrics move. It is a peer-review community for creative work, which is a real and
underserved product — essentially the creator-facing sibling of your business product, with
feedback as the currency instead of views.

Two conditions: run it as a **separate pool** from the consumer panel and never fulfil
business studies from it (§10.5), and pay credits for *quality* of critique, judged by the
recipient, not for volume.

**B. Business-owner barter for B2B studies — a good cold-start mechanic.**
A small-business owner answers 20 studies and earns credits toward running their own. Clean:
no platform involved, and it's straightforward barter for your own service. Restrict it to
**B2B studies**, where business owners are the correct respondent population rather than a
contaminant. This is a genuinely useful way to seed the marketplace before you have cash
customers.

**C. An unrewarded discovery feed.**
Members can browse each other's work, with no credits, no obligation, no reciprocity
tracking. Perfectly legitimate, because nothing is incentivized — but it is a community
feature, not a business. Worth building later for retention; not worth building for revenue.

### 10.8 Summary

| Model | Revenue? | Platform-safe? | Builds the panel? |
|---|---|---|---|
| Credit exchange for live views (purchasable) | Yes | **No** | **No — contaminates it** |
| Credit exchange for live views (not purchasable) | **No** | **No** | **No — contaminates it** |
| Creator feedback exchange (uploaded assets) | Yes | **Yes** | Separate pool, cleanly |
| B2B barter for study participation | Indirect | **Yes** | Yes, for B2B |
| Unrewarded discovery feed | No | **Yes** | Retention only |

The pattern across this document holds again: **every clean mechanism keeps the platform out
of the loop entirely.** The moment a member's action has to register on YouTube or Instagram
for the product to work, you're in the same place regardless of whether the incentive was a
nickel or a credit.

---

## 11. The hybrid: paid tier buys out of viewing, free tier fills the orders

**The proposal:** two tiers. Paid members pay cash and don't have to view anyone. Free
members earn credits by viewing, and their viewing fills the paid members' orders.

This is a real answer to §10.1 — money genuinely enters the system now. It's also the
classic traffic-exchange architecture (EasyHits4U, 10KHits, Hitleap), so it's a proven
*structure*. The problem is what's flowing through it.

### 11.1 It doesn't escape the dilemma — it lands on both horns at once

Trace the two paths separately:

- **Paid member:** pays cash → receives views on their social content. That is a paid view. The fact that free members' labour is what produced it doesn't change what the buyer bought.
- **Free member:** performs views → receives credits → redeems for views. That is an engagement pod, exactly as in §10.

So the hybrid is **simultaneously** paid views *and* a pod. It doesn't dodge either failure
mode; it runs both concurrently, with the pod supplying the paid views.

### 11.2 Selling the paid tier destroys the "credits aren't compensation" argument

In §10 the weakest point of a pure credit exchange was arguing that credits have no cash
value. **The paid tier eliminates that argument, and you eliminate it yourself.**

If a paid member pays $29 for 1,000 views, you have publicly established that a view is
worth $0.029 and a credit is worth whatever your rate card says. Free members earning
credits are now demonstrably earning money-equivalent, at a rate **you published**.

That also makes the barter tax exposure in §10.6 concrete rather than theoretical — the
value is documented — and it means paying contributors in scrip redeemable only for your own
services, which is its own uncomfortable posture. **[counsel]**

### 11.3 It makes the paying customers the most conspicuous accounts in the system

The graph problem from §10.3 gets worse, and it now targets the people paying you.

Free members both give and receive, so they look roughly reciprocal. Paid members
**receive only** — a cluster of accounts absorbing concentrated engagement from a bounded
viewer population and returning none.

Zero-reciprocity concentration is close to the textbook signature of purchased engagement.
You would be selling your paying customers the most detectable position in the network,
which is an unusually bad thing to charge for.

### 11.4 The economics are roughly 8x worse than the same structure carrying opinions

Hold the architecture fixed and change only what members produce:

| | Views | Feedback |
|---|---|---|
| Paid tier | $29/mo → 1,000 views | $49/mo → 50 critiques |
| Member labour to fill it | 1,000 × 30s = **8.3 hours** | 50 × 2 min = **1.7 hours** |
| Revenue per hour of member time | **$3.48** | **$29.40** |

Same two tiers. Same fill mechanic. Same free-labour-serves-paid-orders design. **Eight times
the revenue per hour of member effort**, because an opinion is worth more than an eyeball.

That gap is the entire thesis of this plan, restated inside your own structure.

### 11.5 The one real distinction worth knowing

Website traffic exchanges have run for twenty years without much interference, and social
engagement exchanges get shut down. The difference isn't enforcement appetite — it's that
**a website exchange has no third-party victim.** The site owner wants the traffic; no
platform's metrics are being inflated; there's nobody to complain.

Point the same machine at YouTube or Instagram and a third party's measurement system is
now being corrupted, which is exactly where enforcement lives.

So if an exchange is something you're determined to build, aiming it at plain websites
(with the ad-network block from [05-risk-compliance.md](05-risk-compliance.md) §2 still
absolute) is far safer than aiming it at social platforms. I still wouldn't build it — it's
low-margin, it's the business model most likely to cost you Stripe, and it fills the
community with marketers instead of consumers — but the platform-policy objection largely
evaporates, and that distinction is worth understanding rather than blurring.

### 11.6 Keep the structure. Change the labour.

Your marketplace design is sound. Free contributors service paid orders; paying customers
buy their way out of contributing. That's a legitimate and well-proven two-sided mechanic,
and it solves cold start. **The only thing wrong with it is the unit of work.**

**Creator Exchange** — free members critique other members' uploaded thumbnails, titles and
creative, earning credits. Paid members skip contributing and buy critiques outright.
No platform is touched by anyone. Creators are a real, underserved market with money.

**B2B Study Exchange** — free members answer research studies as respondents, earning
credits. Paid members buy studies with cash. Free members' responses fill paid members'
studies. Restrict to B2B studies where business owners are the correct respondents
([01-strategy.md](01-strategy.md) §5), and it seeds the marketplace at near-zero cost.

Both are the identical architecture you proposed. Both are clean. Both build the data asset
instead of contaminating it. Neither can cost a member their account.

### 11.7 Summary

| Variant | Money in? | Platform-safe? | $/hr of member labour | Builds the asset? |
|---|---|---|---|---|
| Pure credit pod (§10) | No | No | — | No |
| **Hybrid: paid buys out, free fills** | **Yes** | **No — both failure modes** | **~$3.50** | **No** |
| Website-only exchange | Yes | Mostly¹ | ~$3.50 | No |
| **Creator Exchange (critiques)** | **Yes** | **Yes** | **~$29** | **Yes, separate pool** |
| **B2B Study Exchange** | **Yes** | **Yes** | **~$29** | **Yes** |

¹ no third-party platform involved, but the ad-network block still applies absolutely, and
the processor and panel-contamination objections in §11.5 remain.
