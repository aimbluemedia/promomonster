import { PageHeading, SampleDataNotice } from "@/components/app-shell";
import { Card } from "@/components/ui";

export const metadata = { title: "Profile" };

const groups = [
  {
    title: "About you",
    fields: ["Age band", "Gender", "State", "ZIP code", "Household size", "Children at home"],
  },
  {
    title: "Household",
    fields: ["Household income band", "Own or rent", "Vehicles", "Pets"],
  },
  {
    title: "Work",
    fields: ["Employment status", "Industry", "Job function", "Company size"],
  },
  {
    title: "Shopping plans",
    fields: ["Home improvement", "Insurance", "Vehicle", "Major appliance", "Professional services"],
  },
];

export default function ProfilePage() {
  return (
    <>
      <PageHeading
        title="Your profile"
        description="Completing this once unlocks higher-paying studies that match you. Takes about four minutes and pays $0.50."
      />
      <SampleDataNotice>
        The questionnaire and panel_profiles table are Phase 1 — see
        docs/03-product-spec.md §3.2. This is the data that lets targeted
        studies be sold, so it ships in the first release even though nothing
        consumes it until later.
      </SampleDataNotice>

      <div className="grid gap-5 sm:grid-cols-2">
        {groups.map((group) => (
          <Card key={group.title}>
            <h2 className="font-semibold tracking-tight">{group.title}</h2>
            <ul className="mt-3 space-y-1.5 text-sm text-muted">
              {group.fields.map((field) => (
                <li key={field}>{field}</li>
              ))}
            </ul>
          </Card>
        ))}
      </div>

      <Card className="mt-5">
        <p className="text-sm text-muted">
          We never share your name, email or contact details with businesses.
          Studies are matched on profile attributes only, and answers are
          reported in aggregate.
        </p>
      </Card>
    </>
  );
}
