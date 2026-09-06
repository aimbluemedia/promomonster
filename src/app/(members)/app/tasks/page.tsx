import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card } from "@/components/ui";

export const metadata = { title: "Available studies" };

const tasks = [
  { name: "Website feedback — home services", pay: "$0.35", time: "~90 sec" },
  { name: "Which headline is clearer?", pay: "$0.42", time: "~2 min" },
  { name: "Website feedback — online store", pay: "$0.35", time: "~90 sec" },
];

export default function TasksPage() {
  return (
    <>
      <PageHeading
        title="Available studies"
        description="Pick any one. There are no requirements and nothing is assigned to you."
      />
      <SampleDataNotice>
        Task leasing, the timer and response capture are Phase 1 — see
        docs/03-product-spec.md §3.4 and §5.
      </SampleDataNotice>

      <div className="space-y-3">
        {tasks.map((task) => (
          <Card
            key={task.name}
            className="flex flex-wrap items-center justify-between gap-4"
          >
            <div>
              <div className="font-medium">{task.name}</div>
              <div className="mt-0.5 text-sm text-muted">{task.time}</div>
            </div>
            <div className="flex items-center gap-4">
              <span className="text-lg font-semibold text-earn">
                {task.pay}
              </span>
              <button
                type="button"
                disabled
                className="rounded-lg bg-earn px-4 py-2 text-sm font-semibold text-white opacity-50"
              >
                Start
              </button>
            </div>
          </Card>
        ))}
      </div>

      <Card className="mt-6">
        <h2 className="font-semibold tracking-tight">How to not get rejected</h2>
        <ul className="mt-3 space-y-1.5 text-sm text-muted">
          <li>Actually look at the page before answering.</li>
          <li>Write what you genuinely thought — short and blunt is fine.</li>
          <li>One-word and copy-pasted answers get rejected.</li>
          <li>Some studies include an attention check. Read the question.</li>
        </ul>
      </Card>
    </>
  );
}
