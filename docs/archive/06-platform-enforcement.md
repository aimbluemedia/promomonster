# 06 — Platform Enforcement: What Actually Happens

You asked to be walked through why I cut paid social views and paid search clicks. This is
that walkthrough: for each platform, what the rule is, **how it's enforced mechanically**,
**who absorbs the penalty**, and a concrete worst-case incident. Then the decision.

I've flagged where I'm confident and where I'm not, because the honest answer differs a lot
by platform.

The single sentence version: **for every one of these products, the penalty lands on your
customer's asset, not on yours.** You collect $50 and they lose a channel, a domain, or an
income stream. That asymmetry — not the legality — is the reason to cut them.

---

## 1. Google AdSense and ad networks — *the certain one*

**Confidence: very high.** This is the least ambiguous item in this document.

**The rule.** Google's publisher policies define invalid traffic to include clicks and
impressions from incentivized sources. Publishers must not use traffic sources that
generate invalid activity.

**How it's enforced.** Google's invalid-traffic detection is the most mature system
discussed here — it's been adversarially developed for two decades against people
motivated by direct financial gain. It is not a spot check; it's continuous statistical
analysis of traffic patterns per publisher.

**Who absorbs it.** The publisher. Your customer.

**Consequence.** Account disabled. Withheld earnings — typically the entire unpaid
balance, not just the portion deemed invalid. Bans are effectively permanent; appeals
rarely succeed, and Google is not required to explain.

**Worst case.** A niche-site owner earning $3,200/month from AdSense buys 2,000 visits from
you to "grow the audience." Within a few weeks the account is disabled with the month's
earnings withheld. Their entire business income is gone, permanently, and they have your
invoice. Whatever your terms say, you are the proximate cause, you will be named publicly,
and the first result for "PromoMonster" becomes that story.

**Verdict: hard block, non-negotiable.** Ask at signup, scan the page for ad-network
markers at campaign approval, reject automatically. This is in the plan already.

---

## 2. YouTube — *high confidence, catastrophic tail*

**The rule.** YouTube's Fake Engagement policy requires views and engagement to come from
genuine user interest, and the Terms of Service prohibit using the service to artificially
increase view counts. There is no carve-out for "the view was from a real human." The
policy is about *why* the view happened, not *who* generated it. Paying someone to watch
is exactly the thing being described.

**How it's enforced.** Views are counted, then audited — often days later. YouTube's
validation looks at traffic source, watch-time distribution, referrer patterns, device and
IP clustering, and engagement ratios. Paid-visit traffic has a distinctive signature:
a burst of views from diverse residential IPs, tightly clustered short watch times, near-zero
likes/comments relative to views, an unusual concentration of one referrer domain, and no
returning sessions. That is close to a textbook description of what the audit exists to find.

**Who absorbs it.** The channel.

**Consequence, escalating.** View counts scrubbed (most common). Demonetization of the
video. Video removal. Channel strike. Termination for repeated or egregious cases. If the
channel is monetized, separate invalid-traffic action on their AdSense.

**Worst case.** A creator is 300 watch-hours short of the YouTube Partner Program threshold
after eighteen months of work. They buy 5,000 "discovery views" from you. Views land, they
cross the threshold, they apply. The YPP application triggers a human and automated review
of traffic history, inorganic traffic is identified, the application is rejected and the
channel is flagged. Reapplication is possible but now starts from a position of distrust.
Eighteen months of work, and the transaction that cost it was $250 to you.

**Where I'm less certain.** YouTube doesn't publish detection thresholds. Low-volume,
genuinely-human, geographically-diverse views may pass unnoticed for a long time. The
problem isn't that detection is guaranteed — it's that **you cannot detect it, cannot
control it, cannot warn the customer when it's happening, and the downside is
disproportionate to the $250 you earned.** You'd be selling a lottery ticket where the
customer holds the losing end.

---

## 3. TikTok, Instagram, Facebook — *high confidence, invisible damage*

**The rule.** TikTok's Community Guidelines prohibit fake engagement, explicitly including
purchasing or trading views. Meta's terms and Community Standards prohibit inauthentic
behavior and the buying or selling of engagement.

**How it's enforced.** Content removal and account restriction exist, but the primary
mechanism on all three is **distribution suppression** — the content is simply shown to
fewer people. No notification. No entry in any dashboard. No appeal, because there's
nothing to appeal.

**Who absorbs it.** The account.

**Why this is the worst one to sell.** With AdSense, the customer at least *knows* what
happened. Here, your customer's reach quietly degrades and they never learn why. They may
even conclude your product worked (the view count went up) while their organic reach is
being throttled underneath. You would be selling a product whose principal harm is
undetectable by both parties. There is no version of that I can recommend.

---

## 4. Reddit — *high confidence, permanent and domain-wide*

**The rule.** Reddit's Content Policy prohibits vote manipulation and content manipulation,
including coordinated inauthentic activity and directing groups to content to influence its
performance. Paid traffic to a thread is squarely inside that, even with no vote requested.

**How it's enforced.** Reddit's anti-manipulation systems are aggressive and pattern-based,
and moderators of large subreddits actively hunt this.

**Who absorbs it — and this is the part that makes Reddit distinctly bad.** Enforcement
operates at the **domain** level, not just the post or account. Reddit can suppress a domain
sitewide, after which every link to that domain — from anyone, in any subreddit, forever —
is auto-removed. Users don't see a removal message; the link just doesn't appear.

**Worst case.** You send 500 visits to a customer's post promoting their SaaS. Reddit's
systems flag the pattern and the domain is banned sitewide. That company can now never be
linked on Reddit again, by anyone, including happy customers organically recommending them.
For a startup that treats Reddit as a growth channel, this is amputating a limb.

**Appeals** are possible via r/reddit.com modmail and are famously slow and inconsistent.

---

## 5. Google Search / SERP clicking — *low confidence on penalty, high confidence on futility*

This is the one where I want to be most careful, because the honest answer is messier and
the case against it is different from the others.

**The rule.** Google's spam policies prohibit manipulating ranking signals; Search
Essentials require content and behavior aimed at people rather than at rankings.

**What's actually established.** Testimony and exhibits in the 2023 DOJ antitrust trial
confirmed that Google uses user-interaction data in ranking through systems described in
those documents (Navboost among them). So the mechanism click-services are targeting is
real, not folklore. That's why the category exists.

**What is *not* established.** I'm not aware of Google publicly announcing manual actions
specifically for click manipulation, or of a documented case of a site being penalized for
purchased SERP clicks. Anyone telling you the penalty risk is proven is overstating it.
SerpClix has operated openly for years.

**So the real case against it is different — it's three things:**

1. **It probably doesn't work at the volumes you'd sell.** Google heavily filters and
   time-decays interaction signals and operates at a scale where 200 clicks is
   indistinguishable from noise. You'd be selling an outcome you cannot demonstrate.
2. **That makes it a refund and chargeback problem, and chargebacks are how you lose
   Stripe.** Processors act on dispute rates near 1%. A product that can't demonstrate it
   worked, sold to SMBs who expected rankings, generates disputes at a rate that endangers
   the payment rails for the *entire* company — including the profitable research business.
3. **You'd be transferring an unquantified risk to a customer who can't evaluate it.**
   SerpClix sells to SEO professionals who understand exactly what they're buying. Selling
   the same thing to a dentist who thinks they're buying marketing is a materially
   different act.

**Verdict: don't sell live SERP clicking.** But note this is the one item where, if you
decide otherwise, my objection is commercial and ethical rather than "you will be
penalized." I'd rather be precise than scary.

**Keep the simulated version.** Show panelists a realistic search results page containing
your customer's listing and three real competitors, ask which they'd click and why. That
tests the exact thing the customer actually cares about — *does my listing win attention
against my competitors* — produces a quotable answer, touches nobody's ranking, and sells
for 15 credits instead of 1.5.

---

## 6. What replaces them

Every insight the cut products supposedly delivered, available legitimately at higher margin.
[07-social-product-design.md](07-social-product-design.md) specs these out — how to put
social content in front of panelists without the platform ever registering it.

| They wanted | Cut product | Replacement | Credits |
|---|---|---|---|
| "Does my video get clicked?" | Paid YouTube views | **Creative Test** — real thumbnail + title + first 3s; would you click, what did you expect, what would make you click? | 15 |
| "Does my listing win in search?" | Paid SERP clicks | **Search Result Test** — your snippet vs 3 real competitors, which and why | 15 |
| "Is my social content working?" | Paid IG/TikTok views | **Creative Test** on the actual asset | 15 |
| "Is my Reddit post compelling?" | Paid Reddit traffic | **Headline/Hook Test** on the title and opening | 15 |

Ten times the revenue per unit, zero platform exposure, and a deliverable the customer can
act on. "500 people saw your video" changes nothing. "68% couldn't tell what your video was
about from the thumbnail, and 41 of them said the text was unreadable on mobile" changes the
thumbnail.

**That's the actual argument.** Not that the cut products are too risky — that they're worse
products. The paid view tells your customer nothing they can use.

---

## 7. If you want them anyway

Your call, and I'll build it. What I'd insist on:

1. **Separate product line, separate legal entity, separate Stripe account.** A blowup or a processor termination must not reach PromoMonster Panel. This is the non-negotiable one — it protects the business that actually makes money.
2. **Never sold to unsophisticated buyers.** Gate behind self-certification of professional SEO/marketing status. Blocked for accounts that signed up through local-business channels.
3. **Typed acknowledgment before purchase**, not a checkbox: the customer types the name of the platform to confirm they've read what can happen to their channel or domain.
4. **Indemnity and disclaimer** in the terms, drafted by counsel, plus a support macro for the incident that eventually happens.
5. **Never on AdSense-monetized destinations** regardless — item 1 has no safe version.
6. **Volume caps and pacing**, so nothing looks like a burst.
7. **Accept the underwriting cost.** Offering these makes your Stripe application worse and pushes you toward high-risk processing at 4–6% plus a rolling reserve, on the entire business.

Given you're solo on under $25k, item 1 alone — a second entity, second processor, separate
terms — is realistically $3–5k and a meaningful chunk of your runway, spent to enable your
lowest-margin, highest-risk products. **I'd defer the whole question to year two** and
revisit when the research business is funding itself.

---

## 8. Sources and staleness

Policies referenced: Google Publisher Policies (invalid traffic), Google Search spam
policies and Search Essentials, YouTube Terms of Service and Fake Engagement policy, TikTok
Community Guidelines (fake engagement), Meta Terms and Community Standards (inauthentic
behavior), Reddit Content Policy (vote and content manipulation), X platform manipulation
and spam policy.

Platform policies change. Re-read the current text before making a final call, and
re-check annually — the enforcement posture on incentivized traffic has tightened
consistently for a decade and there's no sign of that reversing.
