import { PageHeading } from "@/components/app-shell";
import { StudyBuilder } from "./study-builder";

export const metadata = { title: "New study" };

export default function NewStudyPage() {
  return (
    <>
      <PageHeading
        title="New study"
        description="Six steps, about two minutes."
      />
      <StudyBuilder creditBalance={1240} />
    </>
  );
}
