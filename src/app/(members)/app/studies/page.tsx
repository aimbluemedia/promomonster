import Link from "next/link";
import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card } from "@/components/ui";

export const metadata = { title: "Studies" };

const studies = [
  { name: "Homepage first impressions", type: "Site feedback", state: "Collecting", n: "182 / 250", cost: "3,000 cr" },
  { name: "Pricing headline A/B", type: "Head-to-head", state: "Complete", n: "100 / 100", cost: "1,500 cr" },
  { name: "Competitor search listing", type: "Search listing", state: "In review", n: "0 / 150", cost: "2,250 cr" },
];

export default function StudiesPage() {
  return (
    <>
      <PageHeading
        title="Studies"
        description="Every study you've run, and what came back."
        action={
          <Link
            href="/app/studies/new"
            className="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90"
          >
            New study
          </Link>
        }
      />
      <SampleDataNotice>
        The study builder and live results view are Phase 1 — see
        docs/03-product-spec.md §4.3.
      </SampleDataNotice>
      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b border-line text-left text-xs uppercase tracking-wider text-faint">
            <tr>
              <th className="px-5 py-3 font-semibold">Study</th>
              <th className="hidden px-5 py-3 font-semibold sm:table-cell">Type</th>
              <th className="px-5 py-3 font-semibold">Responses</th>
              <th className="hidden px-5 py-3 font-semibold sm:table-cell">Cost</th>
              <th className="px-5 py-3 font-semibold">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {studies.map((study) => (
              <tr key={study.name}>
                <td className="px-5 py-3.5 font-medium">
                  <Link
                    href="/app/studies/homepage-first-impressions"
                    className="hover:text-brand hover:underline"
                  >
                    {study.name}
                  </Link>
                </td>
                <td className="hidden px-5 py-3.5 text-muted sm:table-cell">{study.type}</td>
                <td className="px-5 py-3.5 font-mono text-xs text-muted">{study.n}</td>
                <td className="hidden px-5 py-3.5 font-mono text-xs text-muted sm:table-cell">{study.cost}</td>
                <td className="px-5 py-3.5 text-muted">{study.state}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </>
  );
}
