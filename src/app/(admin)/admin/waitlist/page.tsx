import { PageHeading } from "@/components/app-shell";
import { Card } from "@/components/ui";
import { listWaitlist } from "@/lib/waitlist";

export const metadata = { title: "Waitlist" };
export const dynamic = "force-dynamic";

function formatDate(value: string | Date | undefined) {
  if (!value) return "—";
  const date = typeof value === "string" ? new Date(value) : value;
  return Number.isNaN(date.getTime())
    ? "—"
    : date.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
      });
}

export default async function WaitlistPage() {
  const rows = await listWaitlist();

  return (
    <>
      <PageHeading
        title="Waitlist"
        description="Live signups from the public site. This is the Phase 0 pipeline — businesses here are who you sell the first 20 studies to."
      />

      {rows.length === 0 ? (
        <Card>
          <p className="text-[15px] text-muted">
            No signups yet. Submit the form on{" "}
            <span className="font-mono text-sm">/earn</span> or{" "}
            <span className="font-mono text-sm">/business</span> to see rows
            here.
          </p>
        </Card>
      ) : (
        <Card className="overflow-x-auto p-0">
          <table className="w-full min-w-[720px] text-sm">
            <thead className="border-b border-line text-left text-xs uppercase tracking-wider text-faint">
              <tr>
                <th className="px-5 py-3 font-semibold">When</th>
                <th className="px-5 py-3 font-semibold">Role</th>
                <th className="px-5 py-3 font-semibold">Name</th>
                <th className="px-5 py-3 font-semibold">Email</th>
                <th className="px-5 py-3 font-semibold">Detail</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {rows.map((row, i) => (
                <tr key={`${row.email}-${i}`}>
                  <td className="whitespace-nowrap px-5 py-3.5 text-muted">
                    {formatDate(row.createdAt)}
                  </td>
                  <td className="px-5 py-3.5">
                    <span
                      className={`rounded-md px-2 py-0.5 text-xs font-semibold ${
                        row.role === "business"
                          ? "bg-brand/10 text-brand"
                          : "bg-earn/10 text-earn"
                      }`}
                    >
                      {row.role}
                    </span>
                  </td>
                  <td className="px-5 py-3.5 font-medium">{row.name ?? "—"}</td>
                  <td className="px-5 py-3.5 font-mono text-xs">{row.email}</td>
                  <td className="max-w-md px-5 py-3.5 text-muted">
                    {row.role === "business"
                      ? [row.company, row.website, row.goal]
                          .filter(Boolean)
                          .join(" · ") || "—"
                      : [row.region, row.postalCode].filter(Boolean).join(" ") ||
                        "—"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </>
  );
}
