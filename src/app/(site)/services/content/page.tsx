import type { Metadata } from "next";
import { Button, Card, Container, Eyebrow } from "@/components/ui";

export const metadata: Metadata = {
  title: "Content testing",
  description:
    "Find out whether your article, landing page or guide actually lands. Real readers tell you what they took away, where they lost interest and what they'd do next.",
};

export default function ContentPage() {
  return (
    <>
      <section className="border-b border-line py-20">
        <Container>
          <div className="max-w-3xl">
            <Eyebrow>PromoMonster Content</Eyebrow>
            <h1 className="text-4xl font-semibold leading-[1.1] tracking-tight sm:text-5xl">
              You wrote it. Does it land?
            </h1>
            <p className="mt-6 text-lg leading-relaxed text-muted">
              Publishing an article and watching the traffic number is not
              feedback. Send it to 50 real readers and find out what they
              actually took away, where they stopped caring, and whether
              they&rsquo;d trust the business behind it.
            </p>
            <div className="mt-9">
              <Button href="/business#start">Test a piece of content</Button>
            </div>
          </div>
        </Container>
      </section>

      <section className="py-16">
        <Container>
          <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
            The questions worth asking
          </h2>
          <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {[
              {
                title: "Comprehension",
                body: "In one sentence, what was this article about? If readers can't answer, nothing else in it matters.",
              },
              {
                title: "Drop-off",
                body: "Where did you lose interest? Pinpoints the paragraph that's costing you the rest of the page.",
              },
              {
                title: "Credibility",
                body: "Did this feel written by someone who knows the subject? The answer to this is usually blunt and useful.",
              },
              {
                title: "Takeaway",
                body: "What's the one thing you'd remember tomorrow? Tells you whether your actual point survived the draft.",
              },
              {
                title: "Next action",
                body: "What would you do after reading this? Reveals whether your call to action is doing anything at all.",
              },
              {
                title: "Headline test",
                body: "Two titles, head to head, with reasons. Cheapest possible way to double a click-through rate.",
              },
            ].map((item) => (
              <Card key={item.title}>
                <h3 className="font-semibold tracking-tight">{item.title}</h3>
                <p className="mt-2 text-[15px] leading-relaxed text-muted">
                  {item.body}
                </p>
              </Card>
            ))}
          </div>
        </Container>
      </section>

      <section className="border-t border-line py-16">
        <Container>
          <div className="max-w-3xl">
            <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
              Why not just buy traffic?
            </h2>
            <p className="mt-4 text-[15px] leading-relaxed text-muted">
              Because a visit tells you nothing. A thousand people landing on
              your article and leaving produces one number and no explanation,
              and it quietly pollutes your analytics while it does it.
            </p>
            <p className="mt-4 text-[15px] leading-relaxed text-muted">
              Fifty people telling you that your opening paragraph buries the
              point, and that three of them thought you were selling something
              you don&rsquo;t sell, is worth more than any traffic number —
              and it costs less.
            </p>
          </div>
        </Container>
      </section>
    </>
  );
}
