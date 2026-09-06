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
