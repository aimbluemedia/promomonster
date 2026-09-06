import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card } from "@/components/ui";

export const metadata = { title: "Approvals" };

const checks = [
  "URL reachable and not on the domain blocklist",
  "Google Safe Browsing clean",
  "No ad-network markers on the page (adsbygoogle, Ezoic, Mediavine, AdThrive, Taboola, Outbrain)",
  "Not a review platform (Google Business, Yelp, Trustpilot, G2, Amazon, App Store, Play Store)",
  "Not a social platform URL for a traffic product",
  "Question text free of prohibited asks — review, subscribe, upvote, click the ad",
];

export default function AdminStudiesPage() {
  return (
    <>
      <PageHeading
        title="Study approvals"
        description="Every campaign is reviewed before its first slot opens. Target SLA is 4 business hours."
      />
      <SampleDataNotice>
        The approval queue lands with Phase 1 — docs/03-product-spec.md §7.
      </SampleDataNotice>

      <Card>
        <h2 className="font-semibold tracking-tight">Automated pre-checks</h2>
        <p className="mt-1.5 text-sm text-muted">
          Run on submission. Any failure blocks activation and routes to manual
          review.
        </p>
        <ul className="mt-4 space-y-2 text-sm text-muted">
          {checks.map((check) => (
            <li key={check} className="flex gap-2.5">
              <span aria-hidden="true" className="text-brand">
                ✓
              </span>
              {check}
            </li>
          ))}
        </ul>
      </Card>

      <Card className="mt-5">
        <h2 className="font-semibold tracking-tight">Always manual</h2>
        <ul className="mt-3 space-y-1.5 text-sm text-muted">
          <li>First campaign from any account</li>
          <li>Any campaign over 500 responses</li>
          <li>Anything an automated check flagged</li>
        </ul>
      </Card>

      <Card className="mt-5">
        <h2 className="font-semibold tracking-tight">States</h2>
        <p className="mt-3 font-mono text-xs leading-relaxed text-muted">
          draft → pending_review → approved → active → (paused) → completed |
          rejected | cancelled
        </p>
      </Card>
    </>
  );
}
