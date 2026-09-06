import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card, Stat } from "@/components/ui";

export const metadata = { title: "Credits" };

const packs = [
  { credits: "500", price: "$47", each: "$0.094" },
  { credits: "1,500", price: "$129", each: "$0.086" },
  { credits: "4,000", price: "$319", each: "$0.080" },
  { credits: "10,000", price: "$749", each: "$0.075" },
];

export default function CreditsPage() {
  return (
    <>
      <PageHeading
        title="Credits"
        description="Credits pay for responses. They don't expire while your account is active."
      />
      <SampleDataNotice>
        Stripe Checkout and the credit ledger land in Phase 1 — see
        docs/02-unit-economics.md §2.
      </SampleDataNotice>

      <Card>
        <Stat value="1,240" label="Credits remaining" />
      </Card>

      <h2 className="mb-4 mt-8 font-semibold tracking-tight">Buy credits</h2>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {packs.map((pack) => (
          <Card key={pack.credits}>
            <div className="text-2xl font-semibold tracking-tight">
              {pack.credits}
            </div>
            <div className="text-sm text-muted">credits</div>
            <div className="mt-4 text-lg font-semibold">{pack.price}</div>
            <div className="font-mono text-xs text-faint">
              {pack.each} each
            </div>
          </Card>
        ))}
      </div>

      <Card className="mt-6">
        <h3 className="font-semibold tracking-tight">What a study costs</h3>
        <ul className="mt-3 space-y-1.5 text-sm text-muted">
          <li>Site feedback response — 12 credits</li>
          <li>Head-to-head / search listing / creative test — 15 credits</li>
          <li>Long-form written response — 20 credits</li>
          <li>Profile-targeted response — 30–60 credits</li>
        </ul>
      </Card>
    </>
  );
}
