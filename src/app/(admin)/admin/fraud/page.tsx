import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card } from "@/components/ui";

export const metadata = { title: "Fraud" };

export default function AdminFraudPage() {
  return (
    <>
      <PageHeading
        title="Fraud"
        description="Signals, queues and the rules that run without a human."
      />
      <SampleDataNotice>
        Detection lands with Phase 1 — docs/03-product-spec.md §6.
      </SampleDataNotice>

      <div className="grid gap-5 md:grid-cols-2">
        <Card>
          <h2 className="font-semibold tracking-tight">
            Automatic rejection — no pay
          </h2>
          <ul className="mt-3 space-y-1.5 text-sm text-muted">
            <li>Submitted before minimum dwell elapsed</li>
            <li>Open text under four words, or keyboard mash</li>
            <li>Text identical to another response in the same study</li>
            <li>Failed attention check</li>
            <li>Response time below the 5th percentile for that study</li>
          </ul>
        </Card>
        <Card>
          <h2 className="font-semibold tracking-tight">
            Flagged — paid, trust score drops
          </h2>
          <ul className="mt-3 space-y-1.5 text-sm text-muted">
            <li>High AI-detection score on open text</li>
            <li>Straight-lining every rating question</li>
            <li>Answers inconsistent with the stated profile</li>
            <li>Device or IP shared with another account</li>
            <li>Datacenter IP, known VPN or proxy range</li>
          </ul>
        </Card>
      </div>

      <Card className="mt-5">
        <h2 className="font-semibold tracking-tight">
          On AI-detection scores
        </h2>
        <p className="mt-2 text-sm leading-relaxed text-muted">
          Treat as a signal, never as proof. These classifiers have real
          false-positive rates, and a wrongly-banned member writes a review that
          outlives the fraud you prevented.
        </p>
      </Card>
    </>
  );
}
