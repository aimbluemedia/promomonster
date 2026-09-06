export type QuestionKind =
  | "short_text"
  | "long_text"
  | "single"
  | "rating"
  | "yes_no";

export type TemplateQuestion = {
  prompt: string;
  kind: QuestionKind;
  options?: string[];
};

export type StudyTemplate = {
  id: string;
  name: string;
  blurb: string;
  /** head_to_head takes two URLs and costs more per response. */
  type: "site_feedback" | "head_to_head";
  urls: 1 | 2;
  questions: TemplateQuestion[];
};

export const CREDITS_PER_RESPONSE = {
  site_feedback: 12,
  head_to_head: 15,
} as const;

/** Roughly $0.086 per credit at the 1,500-credit pack — see docs/02-unit-economics.md §2. */
export const CREDIT_PRICE_USD = 0.086;

export const TEMPLATES: StudyTemplate[] = [
  {
    id: "first-impression",
    name: "First impressions",
    blurb: "What people take away in the first minute on your site.",
    type: "site_feedback",
    urls: 1,
    questions: [
      { prompt: "In your own words, what does this company sell or do?", kind: "short_text" },
      { prompt: "How clear was that from the page?", kind: "rating" },
      { prompt: "What, if anything, confused you?", kind: "long_text" },
      { prompt: "How likely would you be to contact this company?", kind: "rating" },
    ],
  },
  {
    id: "message-clarity",
    name: "Message clarity",
    blurb: "Whether your positioning survives contact with a stranger.",
    type: "site_feedback",
    urls: 1,
    questions: [
      { prompt: "Who do you think this product is for?", kind: "short_text" },
      { prompt: "What problem do you think it solves?", kind: "short_text" },
      { prompt: "How well did the page explain itself?", kind: "rating" },
      { prompt: "What would you want explained better?", kind: "long_text" },
    ],
  },
  {
    id: "pricing-page",
    name: "Pricing page test",
    blurb: "Where people stall between interest and buying.",
    type: "site_feedback",
    urls: 1,
    questions: [
      { prompt: "Which plan would you choose, and why?", kind: "long_text" },
      { prompt: "How easy was the pricing to understand?", kind: "rating" },
      { prompt: "Did anything about the pricing put you off?", kind: "long_text" },
      { prompt: "Does this feel expensive, about right, or cheap?", kind: "single", options: ["Expensive", "About right", "Cheap"] },
    ],
  },
  {
    id: "headline-ab",
    name: "Headline A/B",
    blurb: "Two headlines, head to head, with reasons.",
    type: "head_to_head",
    urls: 2,
    questions: [
      { prompt: "Which headline is clearer?", kind: "single", options: ["Option A", "Option B"] },
      { prompt: "Why did you pick that one?", kind: "long_text" },
      { prompt: "Which makes you more likely to keep reading?", kind: "single", options: ["Option A", "Option B"] },
    ],
  },
  {
    id: "logo-ab",
    name: "Logo or design A/B",
    blurb: "Which mark or layout reads better to people who've never seen either.",
    type: "head_to_head",
    urls: 2,
    questions: [
      { prompt: "Which looks more professional?", kind: "single", options: ["Option A", "Option B"] },
      { prompt: "What made you choose it?", kind: "long_text" },
      { prompt: "What kind of business does each look like?", kind: "long_text" },
    ],
  },
  {
    id: "competitor",
    name: "Competitor comparison",
    blurb: "Your site against a competitor's, judged by a stranger.",
    type: "head_to_head",
    urls: 2,
    questions: [
      { prompt: "Which company would you contact first?", kind: "single", options: ["Option A", "Option B"] },
      { prompt: "Why that one?", kind: "long_text" },
      { prompt: "Which felt more trustworthy?", kind: "single", options: ["Option A", "Option B"] },
    ],
  },
  {
    id: "checkout",
    name: "Checkout friction",
    blurb: "What makes people abandon before paying.",
    type: "site_feedback",
    urls: 1,
    questions: [
      { prompt: "Was anything unclear about how to buy?", kind: "long_text" },
      { prompt: "How much did you trust this site with card details?", kind: "rating" },
      { prompt: "What would have made you more comfortable?", kind: "long_text" },
    ],
  },
  {
    id: "local-trust",
    name: "Local services trust",
    blurb: "Whether a local business looks legitimate to a nearby customer.",
    type: "site_feedback",
    urls: 1,
    questions: [
      { prompt: "Would you trust this business to come to your home?", kind: "yes_no" },
      { prompt: "What made you say that?", kind: "long_text" },
      { prompt: "How professional did the site look?", kind: "rating" },
      { prompt: "What was missing that you'd want to see?", kind: "long_text" },
    ],
  },
];

export const OPEN_KINDS: QuestionKind[] = ["short_text", "long_text"];

export const KIND_LABELS: Record<QuestionKind, string> = {
  short_text: "Short answer",
  long_text: "Long answer",
  single: "Choose one",
  rating: "Rating 1–5",
  yes_no: "Yes / No",
};
