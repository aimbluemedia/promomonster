import type { Metadata } from "next";
import { WaitlistForm } from "@/components/waitlist-form";
import { Card, Container, Eyebrow, Stat } from "@/components/ui";

export const metadata: Metadata = {
  title: "Find out what real people think of your site",
  description:
    "Studies from 50 to 500 real US respondents. First impressions, head-to-head tests, competitor comparisons and ad creative testing — usually back the same day.",
};

const studies = [
  {
    name: "Site feedback",
    price: "from $49",
    body: "Real people visit your page and answer 3–6 questions. What do you sell, how clear was it, what would stop them getting in touch.",
    detail: "50 responses · ~4 hours",
  },
  {
    name: "Head-to-head test",
    price: "from $75",
    body: "Two headlines, two hero images, two offers, two logos. Which wins — and, more usefully, why, in their own words.",
    detail: "50 responses · ~4 hours",
  },
  {
    name: "Search listing test",
    price: "from $75",
    body: "Your listing shown against three real competitors. Which would they click, and what made the difference — reviews, brand, offer, wording.",
    detail: "50 responses · ~6 hours",
  },
  {
    name: "Ad creative test",
    price: "from $75",
    body: "Your thumbnail, title and first three seconds, shown at real feed scale. Would they stop scrolling? What did they think it was about?",
    detail: "50 responses · ~6 hours",
  },
];

export default function BusinessPage() {
  return (
    <>
      <section className="border-b border-line py-20">
        <Container>
          <div className="max-w-3xl">
            <Eyebrow>For businesses</Eyebrow>
            <h1 className="text-4xl font-semibold leading-[1.1] tracking-tight sm:text-5xl">
              Your analytics say they left. We&rsquo;ll tell you why.
            </h1>
            <p className="mt-6 text-lg leading-relaxed text-muted">
              Send your page to 50&ndash;500 real people and get their honest
              first impressions in their own words. No panel minimums, no annual
              contract, no sales call unless you want one.
            </p>
          </div>
          <div className="mt-12 grid max-w-2xl grid-cols-3 gap-8">
            <Stat value="50–500" label="Real respondents per study" />
            <Stat value="~4 hrs" label="Typical turnaround" />
            <Stat value="$0.98" label="Per response, from" />
          </div>
        </Container>
      </section>

      <section className="py-16">
        <Container>
          <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
            What you can run
          </h2>
          <div className="mt-8 grid gap-5 sm:grid-cols-2">
            {studies.map((study) => (
              <Card key={study.name}>
                <div className="flex items-baseline justify-between gap-4">
                  <h3 className="text-lg font-semibold tracking-tight">
                    {study.name}
                  </h3>
                  <span className="shrink-0 text-sm font-semibold text-brand">
                    {study.price}
                  </span>
                </div>
                <p className="mt-2.5 text-[15px] leading-relaxed text-muted">
                  {study.body}
                </p>
                <p className="mt-4 font-mono text-xs text-faint">
                  {study.detail}
                </p>
              </Card>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-t border-line py-16">
        <Container>
          <div className="grid gap-12 md:grid-cols-[1.1fr_1fr]">
            <div>
              <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                What makes the answers worth reading
              </h2>
              <dl className="mt-8 space-y-6">
                {[
                  {
                    term: "Every study has an open-text question",
                    desc: "Ratings tell you something is wrong. Sentences tell you what. The open answers are where the useful part lives, so we require at least one.",
                  },
                  {
                    term: "Low-effort answers get rejected",
                    desc: "Attention checks, minimum response times, duplicate and gibberish detection. If a response is junk, reject it and we re-field it free.",
                  },
                  {
                    term: "Members are paid properly",
                    desc: "Around $8–$16 an hour, well above what micro-task platforms typically pay. People who are paid fairly write real answers.",
                  },
                  {
                    term: "Ordinary people, not marketers",
                    desc: "Our panel is consumers, not other business owners. That sounds obvious and it's the single biggest difference in whether the feedback reflects your actual customers.",
                  },
                ].map((item) => (
                  <div key={item.term}>
                    <dt className="font-semibold">{item.term}</dt>
                    <dd className="mt-1.5 text-[15px] leading-relaxed text-muted">
                      {item.desc}
                    </dd>
                  </div>
                ))}
              </dl>
            </div>

            <div id="start" className="scroll-mt-24">
              <Card>
                <h2 className="text-xl font-semibold tracking-tight">
                  Start your first study
                </h2>
                <p className="mt-2 text-[15px] leading-relaxed text-muted">
                  We&rsquo;re running early studies hands-on, so tell us what
                  you want to learn and we&rsquo;ll set it up with you and get
                  results back within a day or two.
                </p>
                <div className="mt-6">
                  <WaitlistForm
                    role="business"
                    source="business-page"
                    cta="Request a study"
                    note="No card required. We'll reply personally, usually within a day."
                  />
                </div>
              </Card>
            </div>
          </div>
        </Container>
      </section>
    </>
  );
}
