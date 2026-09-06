import Link from "next/link";
import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card, Stat } from "@/components/ui";
import { getSession } from "@/lib/session";

export const metadata = { title: "Overview" };

export default async function MembersOverview() {
  const session = await getSession();
  const isBusiness = session?.role === "business";

  if (isBusiness) {
    return (
      <>
        <PageHeading
          title="Overview"
          description="Your studies at a glance."
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
          Phase 1 wires these tiles to the campaigns and ledger tables in
          docs/04-data-model.md.
        </SampleDataNotice>
        <div className="grid gap-5 sm:grid-cols-3">
          <Card>
            <Stat value="1,240" label="Credits remaining" />
          </Card>
          <Card>
            <Stat value="2" label="Studies running" />
          </Card>
          <Card>
            <Stat value="418" label="Responses collected" />
          </Card>
        </div>
        <Card className="mt-5">
          <h2 className="font-semibold tracking-tight">Recent studies</h2>
          <ul className="mt-4 divide-y divide-line text-sm">
            {[
              { name: "Homepage first impressions", state: "Collecting", n: "182 / 250" },
              { name: "Pricing headline A/B", state: "Complete", n: "100 / 100" },
              { name: "Competitor search listing", state: "In review", n: "0 / 150" },
            ].map((study) => (
              <li
                key={study.name}
                className="flex items-center justify-between gap-4 py-3"
              >
                <span className="font-medium">{study.name}</span>
                <span className="flex items-center gap-4 text-muted">
                  <span className="font-mono text-xs">{study.n}</span>
                  <span>{study.state}</span>
                </span>
              </li>
            ))}
          </ul>
        </Card>
      </>
    );
  }

  return (
    <>
      <PageHeading
        title={`Hi ${session?.name ?? ""}`}
        description="Your earnings and what's available right now."
        action={
          <Link
            href="/app/tasks"
            className="rounded-lg bg-earn px-4 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90"
          >
            Start earning
          </Link>
        }
      />
      <SampleDataNotice>
        Phase 1 wires these to the ledger and task_slots tables in
        docs/04-data-model.md.
      </SampleDataNotice>
      <div className="grid gap-5 sm:grid-cols-3">
        <Card>
          <Stat value="$12.47" label="Available to cash out" />
        </Card>
        <Card>
          <Stat value="$1.35" label="Pending review" />
        </Card>
        <Card>
          <Stat value="$184.62" label="Earned all time" />
        </Card>
      </div>
      <Card className="mt-5">
        <h2 className="font-semibold tracking-tight">Available now</h2>
        <p className="mt-1 text-sm text-muted">
          3 studies match your profile.
        </p>
        <Link
          href="/app/tasks"
          className="mt-4 inline-block text-sm font-semibold text-earn hover:underline"
        >
          See available studies →
        </Link>
      </Card>
    </>
  );
}
