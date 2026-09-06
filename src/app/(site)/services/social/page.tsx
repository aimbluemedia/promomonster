import type { Metadata } from "next";
import { Button, Card, Container, Eyebrow } from "@/components/ui";

export const metadata: Metadata = {
  title: "Social creative testing",
  description:
    "Test your thumbnail, title and hook on real people before you post. Thumb-stop rate, three-second recall and head-to-head creative tests.",
};

export default function SocialPage() {
  return (
    <>
      <section className="border-b border-line py-20">
        <Container>
          <div className="max-w-3xl">
            <Eyebrow>PromoMonster Social</Eyebrow>
            <h1 className="text-4xl font-semibold leading-[1.1] tracking-tight sm:text-5xl">
              Test the thumbnail before you post it.
            </h1>
            <p className="mt-6 text-lg leading-relaxed text-muted">
              Upload your creative — thumbnail, title, first three seconds — and
              we show it to real people at real feed scale. You find out whether
              it stops the scroll and what people think it&rsquo;s about,
              before you commit the post or the ad spend.
            </p>
            <div className="mt-9">
              <Button href="/business#start">Test your creative</Button>
            </div>
          </div>
        </Container>
      </section>

      <section className="py-16">
        <Container>
          <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
            Three tests that tell you something
          </h2>
          <div className="mt-8 grid gap-5 md:grid-cols-3">
            {[
              {
                title: "Thumb-stop test",
                body: "Your creative is dropped into a mock feed alongside neutral filler and people scroll normally. We measure whether they stop, and for how long. It's the only feed metric that matters, and you can't fake it.",
              },
              {
                title: "Three-second recall",
                body: "We show the creative for three seconds — real scroll speed — then take it away and ask what they remember. What was it for? Whose brand was it? This is how professional ad recall testing works.",
              },
              {
                title: "Head-to-head",
                body: "Two thumbnails, two titles, two hooks. Which wins, and why, in their words. Usually the reason is something you'd never have guessed from looking at it yourself.",
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
              What we don&rsquo;t do, and why it protects you
            </h2>
            <p className="mt-4 text-[15px] leading-relaxed text-muted">
              We don&rsquo;t sell views, likes, followers or subscribers, and we
              never send people to your live posts. Paid engagement violates
              every major platform&rsquo;s terms, and the penalty lands on{" "}
              <em>your</em> account, not ours — scrubbed view counts, suppressed
              reach, or a channel strike you can&rsquo;t appeal.
            </p>
            <p className="mt-4 text-[15px] leading-relaxed text-muted">
              So your creative is tested on our platform, not theirs. Nothing
              touches your channel, no metric is inflated, and there is nothing
              for anyone to enforce against. You still learn the thing you
              actually wanted to know — and &ldquo;68% couldn&rsquo;t tell what
              your video was about&rdquo; is far more useful than five hundred
              views that were never going to watch anyway.
            </p>
          </div>
        </Container>
      </section>
    </>
  );
}
