import Link from "next/link";
import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card, Stat } from "@/components/ui";
import { listWaitlist } from "@/lib/waitlist";

export const metadata = { title: "Admin" };
export const dynamic = "force-dynamic";

export default async function AdminOverview() {
  const waitlist = await listWaitlist(1000);
  const businesses = waitlist.filter((row) => row.role === "business").length;
  const panelists = waitlist.filter((row) => row.role === "panelist").length;

  return (
    <>
      <PageHeading
        title="Overview"
        description="Phase 0. Waitlist numbers are live; everything else lands with the platform."
      />

      <div className="grid gap-5 sm:grid-cols-3">
        <Card>
          <Stat value={String(waitlist.length)} label="Waitlist signups" />
        </Card>
        <Card>
          <Stat value={String(businesses)} label="Businesses waiting" />
        </Card>
        <Card>
          <Stat value={String(panelists)} label="Panel members waiting" />
        </Card>
      </div>

      <div className="mt-5">
        <Link
          href="/admin/waitlist"
          className="text-sm font-semibold text-brand hover:underline"
        >
          View the waitlist →
        </Link>
      </div>

      <h2 className="mb-4 mt-9 font-semibold tracking-tight">
        Platform metrics
      </h2>
      <SampleDataNotice>
        These come online with Phase 1. Outstanding panelist liability is a real
        balance-sheet figure — docs/03-product-spec.md §8 requires it from the
        first release.
      </SampleDataNotice>
      <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <Stat value="—" label="MRR" />
        </Card>
        <Card>
          <Stat value="—" label="Credits sold vs consumed" />
        </Card>
        <Card>
          <Stat value="—" label="Outstanding panelist liability" />
        </Card>
        <Card>
          <Stat value="—" label="Blended gross margin" />
        </Card>
      </div>
    </>
  );
}
