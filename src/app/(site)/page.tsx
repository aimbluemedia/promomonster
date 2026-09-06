import { Button, Card, Container, Eyebrow } from "@/components/ui";

export default function HomePage() {
  return (
    <>
      <section className="border-b border-line py-20 sm:py-28">
        <Container>
          <div className="max-w-3xl">
            <Eyebrow>Consumer research, without the enterprise price tag</Eyebrow>
            <h1 className="text-4xl font-semibold leading-[1.08] tracking-tight sm:text-6xl">
              Real people.
              <br />
              Real answers.
            </h1>
            <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted">
              Find out what actual humans think of your website, your ad
              creative and your competitors — 50 to 500 of them, answering in
              their own words, usually back the same day.
            </p>
            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
              <Button href="/business#start">Find out what people think</Button>
              <Button href="/earn" variant="secondary">
                Get paid for your opinion
              </Button>
            </div>
          </div>
        </Container>
      </section>

      <section className="py-16 sm:py-20">
        <Container>
          <div className="grid gap-6 md:grid-cols-2">
            <Card className="flex flex-col">
              <Eyebrow>For businesses</Eyebrow>
              <h2 className="text-2xl font-semibold tracking-tight">
                Stop guessing why visitors leave
              </h2>
              <p className="mt-3 flex-1 text-[15px] leading-relaxed text-muted">
                Your analytics tell you people bounced. They don&rsquo;t tell
                you why. We send real people to your page and ask them — what do
                you think this company sells, what would stop you getting in
                touch, which of these two headlines is clearer.
              </p>
              <ul className="mt-5 space-y-2 text-[15px] text-muted">
                {[
                  "First impressions of your site, in their words",
                  "Head-to-head tests: two headlines, two designs, two offers",
                  "Your search listing against three real competitors",
                  "Ad creative tested before you spend on the ad",
                ].map((item) => (
                  <li key={item} className="flex gap-2.5">
                    <span aria-hidden="true" className="text-brand">
                      —
                    </span>
                    {item}
                  </li>
                ))}
              </ul>
              <div className="mt-7">
                <Button href="/business">See how it works</Button>
              </div>
            </Card>

            <Card className="flex flex-col">
              <Eyebrow>For panel members</Eyebrow>
              <h2 className="text-2xl font-semibold tracking-tight">
                Get paid to share your opinion
              </h2>
              <p className="mt-3 flex-1 text-[15px] leading-relaxed text-muted">
                Look at a website, answer a few honest questions, get paid.
                Most studies take about ninety seconds and pay $0.35, which
                works out around $8&ndash;$16 an hour. No selling, no
                experience, no minimum hours.
              </p>
              <ul className="mt-5 space-y-2 text-[15px] text-muted">
                {[
                  "Work whenever you want, from any device",
                  "Cash out at $10 — paid weekly",
                  "No fees, ever, and nothing to buy",
                  "18+ and US-based for now",
                ].map((item) => (
                  <li key={item} className="flex gap-2.5">
                    <span aria-hidden="true" className="text-earn">
                      —
                    </span>
                    {item}
                  </li>
                ))}
              </ul>
              <div className="mt-7">
                <Button href="/earn" variant="earn">
                  Join the panel
                </Button>
              </div>
            </Card>
          </div>
        </Container>
      </section>

      <section className="border-t border-line py-16 sm:py-20">
        <Container>
          <h2 className="max-w-2xl text-2xl font-semibold tracking-tight sm:text-3xl">
            One panel of real people, answering the questions your analytics
            can&rsquo;t
          </h2>
          <div className="mt-10 grid gap-8 sm:grid-cols-3">
            {[
              {
                step: "01",
                title: "Tell us what you want to learn",
                body: "Pick a template — first impressions, headline test, competitor comparison — or write your own questions. Takes about two minutes.",
              },
              {
                step: "02",
                title: "Real people answer",
                body: "Your study goes to matched members of our US panel. They visit your page and answer honestly. Attention checks and open-text screening filter out low-effort responses.",
              },
              {
                step: "03",
                title: "Read what they actually said",
                body: "Results arrive as they come in. Charts for the ratings, and every open-text answer in full — which is usually where the useful part is.",
              },
            ].map((item) => (
              <div key={item.step}>
                <div className="font-mono text-sm text-brand">{item.step}</div>
                <h3 className="mt-3 text-lg font-semibold tracking-tight">
                  {item.title}
                </h3>
                <p className="mt-2 text-[15px] leading-relaxed text-muted">
                  {item.body}
                </p>
              </div>
            ))}
          </div>
        </Container>
      </section>
    </>
  );
}
