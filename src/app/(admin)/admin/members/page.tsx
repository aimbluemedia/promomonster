import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card } from "@/components/ui";

export const metadata = { title: "Members" };

export default function AdminMembersPage() {
  return (
    <>
      <PageHeading
        title="Members"
        description="Panel members and business accounts."
      />
      <SampleDataNotice>
        Member records land with Phase 1 — docs/03-product-spec.md §8.
      </SampleDataNotice>

      <div className="grid gap-5 md:grid-cols-2">
        <Card>
          <h2 className="font-semibold tracking-tight">Panel member record</h2>
          <ul className="mt-3 space-y-1.5 text-sm text-muted">
            <li>Trust score and the events that moved it</li>
            <li>Task history with response samples</li>
            <li>IP and device history, and accounts sharing a device</li>
            <li>Payout history and W-9 status</li>
            <li>Suspend, ban, adjust balance — each with a reason and an actor</li>
          </ul>
        </Card>
        <Card>
          <h2 className="font-semibold tracking-tight">Business record</h2>
          <ul className="mt-3 space-y-1.5 text-sm text-muted">
            <li>Credit ledger and campaign history</li>
            <li>Ad-network flag and risk tier</li>
            <li>Rejection rate — capped at 20% before review</li>
            <li>Refund, suspend, note</li>
          </ul>
        </Card>
      </div>

      <Card className="mt-5">
        <h2 className="font-semibold tracking-tight">Ban policy</h2>
        <p className="mt-2 text-sm leading-relaxed text-muted">
          Always give a reason and always allow one appeal to a human. Confirmed
          fraud forfeits the balance, and that is stated in the panel terms.
          Silent bans with confiscated balances are how panel businesses get
          destroyed publicly — docs/03-product-spec.md §6.
        </p>
      </Card>
    </>
  );
}
