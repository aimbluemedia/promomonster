import type { Bucket } from "@/components/charts/distribution";

/**
 * Illustrative results for the study view. Replaced by real queries against
 * responses/campaign_questions in Phase 1 — docs/04-data-model.md.
 */
export type ResultQuestion =
  | { prompt: string; kind: "rating"; buckets: Bucket[] }
  | { prompt: string; kind: "single"; buckets: Bucket[] }
  | { prompt: string; kind: "open"; answers: string[] };

export const SAMPLE_STUDY = {
  id: "homepage-first-impressions",
  name: "Homepage first impressions",
  template: "First impressions",
  url: "https://acmepools.example.com",
  status: "Collecting",
  completed: 182,
  target: 250,
  medianSeconds: 94,
  questions: [
    {
      prompt: "In your own words, what does this company sell or do?",
      kind: "open",
      answers: [
        "Pool cleaning I think? The photos are all pools but it never actually says.",
        "Swimming pool maintenance and repair for homeowners.",
        "Honestly not sure. Something with pools — maybe they build them?",
        "Pool service company. Weekly cleaning, chemicals, that kind of thing.",
        "They install pools. Or service them. The header says both which confused me.",
        "Pool repair. The phone number is the most prominent thing on the page.",
      ],
    },
    {
      prompt: "How clear was that from the page?",
      kind: "rating",
      buckets: [
        { label: "1 — Not at all", count: 24 },
        { label: "2", count: 41 },
        { label: "3", count: 58 },
        { label: "4", count: 39 },
        { label: "5 — Very clear", count: 20 },
      ],
    },
    {
      prompt: "What, if anything, confused you?",
      kind: "open",
      answers: [
        "Couldn't tell if they serve my area. No cities listed anywhere I could find.",
        "No prices at all. Not even a starting-from figure.",
        "The menu has nine items and half of them sound like the same thing.",
        "It says 'award winning' but doesn't say which award.",
        "Took a while to find a way to contact them that wasn't a form.",
      ],
    },
    {
      prompt: "How likely would you be to contact this company?",
      kind: "rating",
      buckets: [
        { label: "1 — Not at all", count: 51 },
        { label: "2", count: 44 },
        { label: "3", count: 47 },
        { label: "4", count: 27 },
        { label: "5 — Very likely", count: 13 },
      ],
    },
  ] satisfies ResultQuestion[],
};
