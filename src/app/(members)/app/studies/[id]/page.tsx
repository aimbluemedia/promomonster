import Link from "next/link";
import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Distribution } from "@/components/charts/distribution";
import { Card, Stat } from "@/components/ui";
import { SAMPLE_STUDY } from "@/lib/sample-results";

export const metadata = { title: SAMPLE_STUDY.name };

export default function StudyResultsPage() {
  const study = SAMPLE_STUDY;
  const pct = Math.round((study.completed / study.target) * 100);

  return (
    <>
      <PageHeading
        title={study.name}
        description={`${study.template} · ${study.url}`}
        action={
          <Link
            href="/app/studies"
            className="rounded-lg border border-line bg-surface px-4 py-2.5 text-sm font-semibold hover:bg-raised"
          >
            All studies
          </Link>
        }
      />
      <SampleDataNotice>
        Illustrative responses. Phase 1 reads these from the responses table —
        docs/04-data-model.md.
      </SampleDataNotice>

      <div className="grid gap-5 sm:grid-cols-3">
        <Card>
          <Stat
            value={`${study.completed} / ${study.target}`}
            label="Responses collected"
          />
          <div
            className="mt-3 h-1.5 rounded-full bg-raised"
            role="img"
            aria-label={`${pct}% complete`}
          >
            <div
              className="h-1.5 rounded-r-[4px] bg-brand"
              style={{ width: `${pct}%` }}
            />
          </div>
        </Card>
        <Card>
          <Stat value={`${study.medianSeconds}s`} label="Median time on page" />
        </Card>
        <Card>
          <Stat value={study.status} label="Status" />
        </Card>
      </div>

      <div className="mt-6 space-y-5">
        {study.questions.map((question, i) => (
          <Card key={i}>
            <h2 className="font-semibold tracking-tight">
              <span className="font-mono text-xs text-faint">Q{i + 1}</span>{" "}
              {question.prompt}
            </h2>

            {question.kind === "open" ? (
              <>
                <p className="mt-1.5 text-sm text-muted">
                  {study.completed} written answers. A sample:
                </p>
                <ul className="mt-4 space-y-2.5">
                  {question.answers.map((answer, j) => (
                    <li
                      key={j}
                      className="rounded-lg border-l-2 border-line bg-raised px-4 py-3 text-[15px] leading-relaxed"
                    >
                      &ldquo;{answer}&rdquo;
                    </li>
                  ))}
                </ul>
                <button
                  type="button"
                  disabled
                  className="mt-4 text-sm font-semibold text-brand opacity-50"
                >
                  Read all {study.completed} answers →
                </button>
              </>
            ) : (
              <div className="mt-4">
                <Distribution
                  buckets={question.buckets}
                  scale={question.kind === "rating" ? "ordinal" : "flat"}
                />
              </div>
            )}
          </Card>
        ))}
      </div>

      <Card className="mt-6">
        <h2 className="font-semibold tracking-tight">Export</h2>
        <p className="mt-1.5 text-sm text-muted">
          CSV of every response, including the open answers in full.
        </p>
        <button
          type="button"
          disabled
          className="mt-4 rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold opacity-50"
        >
          Download CSV
        </button>
      </Card>
    </>
  );
}
