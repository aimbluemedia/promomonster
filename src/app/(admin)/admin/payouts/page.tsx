import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card } from "@/components/ui";

export const metadata = { title: "Payouts" };

export default function AdminPayoutsPage() {
  return (
    <>
      <PageHeading
        title="Payouts"
        description="Weekly batches. $10 minimum. First payout held 7 days for review."
      />
      <SampleDataNotice>
        Batch export lands with Phase 1. Phase 0 runs on a PayPal Mass Payout
        CSV — docs/00-execution-plan.md §3.
      </SampleDataNotice>

      <Card>
        <h2 className="font-semibold tracking-tight">Batch states</h2>
        <p className="mt-3 font-mono text-xs text-muted">
          requested → approved → processing → paid | failed | rejected
        </p>
      </Card>

      <Card className="mt-5">
        <h2 className="font-semibold tracking-tight">Controls that must hold</h2>
        <ul className="mt-3 space-y-1.5 text-sm text-muted">
          <li>One in-flight payout per member, enforced by a partial unique index</li>
          <li>Every payout is a double-entry ledger transaction with an idempotency key</li>
          <li>W-9 collected before the first payout crossing $500 lifetime</li>
          <li>Outstanding panelist liability reconciled nightly against the ledger</li>
        </ul>
      </Card>
    </>
  );
}
