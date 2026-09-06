import type { Metadata } from "next";
import { WaitlistForm } from "@/components/waitlist-form";
import { Card, Container, Eyebrow } from "@/components/ui";

export const metadata: Metadata = {
  title: "Get paid to share your opinion",
  description:
    "Look at a website, answer a few honest questions, get paid. Around $8–$16 an hour, cash out at $10, work whenever you want. US, 18+.",
};

const faqs = [
  {
    q: "How much can I actually make?",
    a: "A typical study takes about ninety seconds and pays $0.35, which works out to roughly $14 an hour while studies are available. We're honest that this is side income, not a job — how much you make depends on how many studies are running that match you. We'd rather tell you that up front than have you find out.",
  },
  {
    q: "When do I get paid?",
    a: "Cash out any time you're above $10. Payouts run weekly by PayPal. Your first one is held about seven days while we verify the account, then it's weekly after that.",
  },
  {
    q: "Is there anything to buy?",
    a: "No. There are no fees, no upgrades, no starter kit, and we will never ask you for money. If anything ever asks you to pay us to earn, it isn't us.",
  },
  {
    q: "What do I actually do?",
    a: "Visit a website for a minute, then answer a few questions about it — what you think the company does, whether anything confused you, which of two headlines is clearer. There are no right answers. Honest reactions are the entire product.",
  },
  {
    q: "Why do some answers get rejected?",
    a: "Studies include attention checks, and one-word or copy-pasted answers get rejected because businesses are paying for real opinions. Write what you actually thought, even if it's short and blunt, and you'll be fine.",
  },
  {
    q: "Who can join?",
    a: "18 or over and based in the US for now. We'll open other countries once payouts and support are running smoothly.",
  },
];

export default function EarnPage() {
  return (
    <>
      <section className="border-b border-line py-20">
        <Container>
          <div className="grid gap-12 md:grid-cols-[1.05fr_1fr] md:items-start">
            <div>
              <Eyebrow>For panel members</Eyebrow>
              <h1 className="text-4xl font-semibold leading-[1.1] tracking-tight sm:text-5xl">
                Get paid to share your opinion.
              </h1>
              <p className="mt-6 text-lg leading-relaxed text-muted">
                Businesses want to know what real people think of their
                websites. You look, you answer honestly, you get paid. Around
                <strong className="font-semibold text-ink"> $8&ndash;$16 an hour</strong>,
                on your own schedule.
              </p>
              <ul className="mt-8 space-y-3 text-[15px]">
                {[
                  "No selling, no calls, no experience needed",
                  "Work whenever you like — no minimum hours",
                  "Cash out at $10, paid weekly by PayPal",
                  "Completely free, with no fees taken from your earnings",
                ].map((item) => (
                  <li key={item} className="flex gap-3">
                    <span aria-hidden="true" className="text-earn">
                      ✓
                    </span>
                    <span className="text-muted">{item}</span>
                  </li>
                ))}
              </ul>
            </div>

            <Card>
              <h2 className="text-xl font-semibold tracking-tight">
                Join the panel
              </h2>
              <p className="mt-2 text-[15px] leading-relaxed text-muted">
                We open spots in batches, so there&rsquo;s always work waiting
                when you log in. Tell us where you are and we&rsquo;ll email you
                when yours is ready.
              </p>
              <div className="mt-6">
                <WaitlistForm
                  role="panelist"
                  source="earn-page"
                  cta="Join the panel"
                  note="Free to join. 18+ and US-based. We'll never ask you for payment."
                />
              </div>
            </Card>
          </div>
        </Container>
      </section>

      <section className="py-16">
        <Container>
          <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
            Straight answers
          </h2>
          <dl className="mt-8 grid max-w-4xl gap-x-10 gap-y-7 sm:grid-cols-2">
            {faqs.map((faq) => (
              <div key={faq.q}>
                <dt className="font-semibold">{faq.q}</dt>
                <dd className="mt-1.5 text-[15px] leading-relaxed text-muted">
                  {faq.a}
                </dd>
              </div>
            ))}
          </dl>
        </Container>
      </section>
    </>
  );
}
