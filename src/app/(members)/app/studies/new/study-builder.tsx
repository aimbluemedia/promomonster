"use client";

import { useMemo, useState } from "react";
import {
  CREDITS_PER_RESPONSE,
  CREDIT_PRICE_USD,
  KIND_LABELS,
  OPEN_KINDS,
  TEMPLATES,
  type StudyTemplate,
  type TemplateQuestion,
} from "@/lib/study-templates";

const SIZES = [50, 100, 250, 500];
const MAX_QUESTIONS = 6;

const steps = ["Goal", "Pages", "Questions", "Audience", "Size", "Review"];

function StepNav({ current }: { current: number }) {
  return (
    <ol className="mb-8 flex flex-wrap gap-x-1.5 gap-y-2 text-xs">
      {steps.map((label, i) => {
        const state =
          i === current ? "current" : i < current ? "done" : "todo";
        return (
          <li key={label} className="flex items-center gap-1.5">
            <span
              className={`rounded-md px-2.5 py-1 font-semibold ${
                state === "current"
                  ? "bg-brand text-white"
                  : state === "done"
                    ? "bg-raised text-ink"
                    : "text-faint"
              }`}
            >
              {i + 1}. {label}
            </span>
            {i < steps.length - 1 && (
              <span aria-hidden="true" className="text-faint">
                ›
              </span>
            )}
          </li>
        );
      })}
    </ol>
  );
}

const field =
  "w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-brand focus:outline-2 focus:outline-offset-1 focus:outline-brand";

export function StudyBuilder({ creditBalance }: { creditBalance: number }) {
  const [step, setStep] = useState(0);
  const [template, setTemplate] = useState<StudyTemplate | null>(null);
  const [urls, setUrls] = useState<string[]>(["", ""]);
  const [questions, setQuestions] = useState<TemplateQuestion[]>([]);
  const [size, setSize] = useState(100);

  const perResponse = template ? CREDITS_PER_RESPONSE[template.type] : 0;
  const credits = perResponse * size;
  const dollars = credits * CREDIT_PRICE_USD;
  const hasOpenQuestion = questions.some((q) => OPEN_KINDS.includes(q.kind));
  const urlsNeeded = template?.urls ?? 1;
  const urlsFilled = urls.slice(0, urlsNeeded).every((u) => u.trim().length > 3);

  const blocked = useMemo(() => {
    if (step === 0) return !template;
    if (step === 1) return !urlsFilled;
    if (step === 2) return questions.length === 0 || !hasOpenQuestion;
    return false;
  }, [step, template, urlsFilled, questions.length, hasOpenQuestion]);

  function choose(next: StudyTemplate) {
    setTemplate(next);
    setQuestions(next.questions.map((q) => ({ ...q })));
  }

  function updateQuestion(index: number, prompt: string) {
    setQuestions((prev) =>
      prev.map((q, i) => (i === index ? { ...q, prompt } : q)),
    );
  }

  return (
    <div>
      <StepNav current={step} />

      {step === 0 && (
        <section>
          <h2 className="text-lg font-semibold tracking-tight">
            What do you want to learn?
          </h2>
          <p className="mt-1 text-sm text-muted">
            Pick a starting point. You can edit every question afterwards.
          </p>
          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            {TEMPLATES.map((item) => {
              const selected = template?.id === item.id;
              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => choose(item)}
                  aria-pressed={selected}
                  className={`rounded-xl border p-4 text-left transition-colors ${
                    selected
                      ? "border-brand bg-brand/5"
                      : "border-line bg-surface hover:bg-raised"
                  }`}
                >
                  <span className="block font-semibold">{item.name}</span>
                  <span className="mt-1 block text-sm text-muted">
                    {item.blurb}
                  </span>
                  <span className="mt-3 block font-mono text-xs text-faint">
                    {CREDITS_PER_RESPONSE[item.type]} credits / response
                  </span>
                </button>
              );
            })}
          </div>
        </section>
      )}

      {step === 1 && template && (
        <section>
          <h2 className="text-lg font-semibold tracking-tight">
            {urlsNeeded === 2 ? "Which two pages?" : "Which page?"}
          </h2>
          <p className="mt-1 text-sm text-muted">
            {urlsNeeded === 2
              ? "People see both and compare them."
              : "People visit this page before answering."}
          </p>
          <div className="mt-5 max-w-xl space-y-3">
            {Array.from({ length: urlsNeeded }).map((_, i) => (
              <div key={i}>
                <label
                  htmlFor={`url-${i}`}
                  className="mb-1.5 block text-sm font-medium"
                >
                  {urlsNeeded === 2 ? `Option ${i === 0 ? "A" : "B"}` : "URL"}
                </label>
                <input
                  id={`url-${i}`}
                  className={field}
                  placeholder="https://example.com/pricing"
                  value={urls[i]}
                  onChange={(e) =>
                    setUrls((prev) =>
                      prev.map((u, j) => (j === i ? e.target.value : u)),
                    )
                  }
                />
              </div>
            ))}
          </div>
          <p className="mt-4 max-w-xl text-xs text-faint">
            Pages running Google AdSense or another ad network can&rsquo;t be
            used — paid visits count as invalid traffic and put the
            publisher&rsquo;s account at risk. Research studies on your own site
            are unaffected.
          </p>
        </section>
      )}

      {step === 2 && (
        <section>
          <h2 className="text-lg font-semibold tracking-tight">Questions</h2>
          <p className="mt-1 text-sm text-muted">
            Up to {MAX_QUESTIONS}. At least one has to be an open answer —
            that&rsquo;s where the useful part comes from.
          </p>
          <div className="mt-5 max-w-2xl space-y-3">
            {questions.map((question, i) => (
              <div
                key={i}
                className="rounded-xl border border-line bg-surface p-4"
              >
                <div className="mb-2 flex items-center justify-between gap-3">
                  <span className="font-mono text-xs text-faint">
                    Q{i + 1} · {KIND_LABELS[question.kind]}
                  </span>
                  <button
                    type="button"
                    onClick={() =>
                      setQuestions((prev) => prev.filter((_, j) => j !== i))
                    }
                    className="text-xs font-semibold text-muted hover:text-ink"
                  >
                    Remove
                  </button>
                </div>
                <input
                  aria-label={`Question ${i + 1}`}
                  className={field}
                  value={question.prompt}
                  onChange={(e) => updateQuestion(i, e.target.value)}
                />
                {question.options && (
                  <p className="mt-2 text-xs text-muted">
                    Options: {question.options.join(" · ")}
                  </p>
                )}
              </div>
            ))}
          </div>
          {questions.length < MAX_QUESTIONS && (
            <button
              type="button"
              onClick={() =>
                setQuestions((prev) => [
                  ...prev,
                  { prompt: "", kind: "long_text" },
                ])
              }
              className="mt-3 rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold hover:bg-raised"
            >
              Add a question
            </button>
          )}
          {!hasOpenQuestion && questions.length > 0 && (
            <p role="alert" className="mt-3 text-sm text-earn">
              Add at least one short or long answer question.
            </p>
          )}
        </section>
      )}

      {step === 3 && (
        <section>
          <h2 className="text-lg font-semibold tracking-tight">Audience</h2>
          <p className="mt-1 text-sm text-muted">
            Who should see this study?
          </p>
          <div className="mt-5 max-w-xl space-y-3">
            <div className="rounded-xl border border-brand bg-brand/5 p-4">
              <span className="font-semibold">General US population</span>
              <p className="mt-1 text-sm text-muted">
                Adults across the United States. Fastest to fill.
              </p>
            </div>
            <div className="rounded-xl border border-dashed border-line p-4 opacity-70">
              <span className="font-semibold">Targeted</span>
              <p className="mt-1 text-sm text-muted">
                State, age, income, homeowner, job function. Available once the
                panel profile data reaches useful volume.
              </p>
            </div>
          </div>
        </section>
      )}

      {step === 4 && (
        <section>
          <h2 className="text-lg font-semibold tracking-tight">
            How many people?
          </h2>
          <p className="mt-1 text-sm text-muted">
            100 is enough to see a clear pattern. 50 is enough to catch
            something badly broken.
          </p>
          <div className="mt-5 flex flex-wrap gap-3">
            {SIZES.map((option) => (
              <button
                key={option}
                type="button"
                onClick={() => setSize(option)}
                aria-pressed={size === option}
                className={`rounded-xl border px-6 py-4 text-center transition-colors ${
                  size === option
                    ? "border-brand bg-brand/5"
                    : "border-line bg-surface hover:bg-raised"
                }`}
              >
                <span className="block text-xl font-semibold">{option}</span>
                <span className="mt-0.5 block font-mono text-xs text-faint">
                  {perResponse * option} cr
                </span>
              </button>
            ))}
          </div>
        </section>
      )}

      {step === 5 && template && (
        <section>
          <h2 className="text-lg font-semibold tracking-tight">Review</h2>
          <dl className="mt-5 max-w-xl divide-y divide-line rounded-xl border border-line bg-surface text-sm">
            {[
              ["Study", template.name],
              [urlsNeeded === 2 ? "Pages" : "Page", urls.slice(0, urlsNeeded).join("  vs  ")],
              ["Questions", `${questions.length}`],
              ["Audience", "General US population"],
              ["Responses", `${size}`],
              ["Cost", `${credits.toLocaleString()} credits`],
            ].map(([label, value]) => (
              <div key={label} className="flex gap-4 px-4 py-3">
                <dt className="w-32 shrink-0 text-muted">{label}</dt>
                <dd className="min-w-0 flex-1 break-words font-medium">
                  {value}
                </dd>
              </div>
            ))}
          </dl>

          <div className="mt-4 max-w-xl rounded-xl border border-line bg-raised p-4 text-sm">
            <div className="flex justify-between">
              <span className="text-muted">Balance after launch</span>
              <span className="font-mono">
                {(creditBalance - credits).toLocaleString()} cr
              </span>
            </div>
            <div className="mt-1 flex justify-between">
              <span className="text-muted">Approximate value</span>
              <span className="font-mono">${dollars.toFixed(2)}</span>
            </div>
            {creditBalance - credits < 0 && (
              <p role="alert" className="mt-3 text-earn">
                Not enough credits. You&rsquo;d need{" "}
                {(credits - creditBalance).toLocaleString()} more.
              </p>
            )}
          </div>

          <button
            type="button"
            disabled
            className="mt-5 rounded-lg bg-brand px-5 py-3 text-sm font-semibold text-white opacity-50"
          >
            Launch study
          </button>
          <p className="mt-2 text-xs text-faint">
            Launching, credit reservation and campaign approval are wired up in
            Phase 1 — docs/03-product-spec.md §5 and §7.
          </p>
        </section>
      )}

      <div className="mt-9 flex items-center gap-3 border-t border-line pt-6">
        <button
          type="button"
          onClick={() => setStep((s) => Math.max(0, s - 1))}
          disabled={step === 0}
          className="rounded-lg border border-line bg-surface px-4 py-2.5 text-sm font-semibold disabled:opacity-40"
        >
          Back
        </button>
        {step < steps.length - 1 && (
          <button
            type="button"
            onClick={() => setStep((s) => s + 1)}
            disabled={blocked}
            className="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-40"
          >
            Continue
          </button>
        )}
        {template && (
          <span className="ml-auto font-mono text-xs text-faint">
            {credits.toLocaleString()} cr
          </span>
        )}
      </div>
    </div>
  );
}
