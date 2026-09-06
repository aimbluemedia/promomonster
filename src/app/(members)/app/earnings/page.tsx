import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card, Stat } from "@/components/ui";

export const metadata = { title: "Earnings" };

const history = [
  { date: "Sep 4", study: "Website feedback — home services", amount: "$0.35", state: "Approved" },
  { date: "Sep 4", study: "Which headline is clearer?", amount: "$0.42", state: "Approved" },
  { date: "Sep 3", study: "Website feedback — dentist", amount: "$0.35", state: "Pending" },
];

export default function EarningsPage() {
  return (
    <>
      <PageHeading
        title="Earnings"
        description="Cash out any time you're above $10. Payouts run weekly."
      />
      <SampleDataNotice>
        The double-entry ledger and payout batching are Phase 1 — see
        docs/04-data-model.md.
      </SampleDataNotice>

      <div className="grid gap-5 sm:grid-cols-3">
        <Card>
          <Stat value="$12.47" label="Available" />
        </Card>
        <Card>
          <Stat value="$1.35" label="Pending review" />
        </Card>
        <Card>
          <Stat value="$184.62" label="All time" />
        </Card>
      </div>

      <div className="mt-5">
        <button
          type="button"
          disabled
          className="rounded-lg bg-earn px-5 py-3 text-sm font-semibold text-white opacity-50"
        >
          Request payout
        </button>
      </div>

      <Card className="mt-6 p-0">
        <table className="w-full text-sm">
          <thead className="border-b border-line text-left text-xs uppercase tracking-wider text-faint">
            <tr>
              <th className="px-5 py-3 font-semibold">Date</th>
              <th className="px-5 py-3 font-semibold">Study</th>
              <th className="px-5 py-3 font-semibold">Amount</th>
              <th className="px-5 py-3 font-semibold">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {history.map((row, i) => (
              <tr key={i}>
                <td className="px-5 py-3.5 text-muted">{row.date}</td>
                <td className="px-5 py-3.5 font-medium">{row.study}</td>
                <td className="px-5 py-3.5 font-mono text-xs">{row.amount}</td>
                <td className="px-5 py-3.5 text-muted">{row.state}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </>
  );
}
