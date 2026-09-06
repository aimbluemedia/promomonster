import { SampleDataNotice } from "@/components/app-shell";
import { TaskRunner } from "./task-runner";

export const metadata = { title: "Study" };

export default function TaskPage() {
  return (
    <>
      <SampleDataNotice>
        The front end of the task flow. Phase 1 adds the server-side lease,
        dwell verification and response storage — docs/03-product-spec.md §3.4
        and §5. The timer here is client-side only and proves nothing on its
        own.
      </SampleDataNotice>
      <TaskRunner />
    </>
  );
}
