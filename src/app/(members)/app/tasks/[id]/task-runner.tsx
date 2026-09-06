"use client";

import { useEffect, useRef, useState } from "react";

type Question =
  | { prompt: string; kind: "short_text" | "long_text" }
  | { prompt: string; kind: "rating" }
  | { prompt: string; kind: "single"; options: string[] };

const QUESTIONS: Question[] = [
  { prompt: "In your own words, what does this company sell or do?", kind: "short_text" },
  { prompt: "How clear was that from the page?", kind: "rating" },
  { prompt: "What, if anything, confused you?", kind: "long_text" },
  {
    prompt: "How likely would you be to contact this company?",
    kind: "single",
    options: ["Very likely", "Somewhat likely", "Not likely"],
  },
];

const MIN_SECONDS = 60;
const PAYOUT = "$0.35";
const TARGET_URL = "https://example.com";

const field =
  "w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-brand focus:outline-2 focus:outline-offset-1 focus:outline-brand";

export function TaskRunner() {
  const [phase, setPhase] = useState<"brief" | "viewing" | "answering" | "done">("brief");
  const [elapsed, setElapsed] = useState(0);
  const [answers, setAnswers] = useState<Record<number, string>>({});
  const startedAt = useRef<number | null>(null);

  useEffect(() => {
    if (phase !== "viewing") return;
    const timer = setInterval(() => {
      if (startedAt.current === null) return;
      const seconds = Math.floor((Date.now() - startedAt.current) / 1000);
      setElapsed(seconds);
      if (seconds >= MIN_SECONDS) setPhase("answering");
    }, 250);
    return () => clearInterval(timer);
  }, [phase]);

  function begin() {
    startedAt.current = Date.now();
    setPhase("viewing");
    window.open(TARGET_URL, "_blank", "noopener,noreferrer");
  }

  const remaining = Math.max(MIN_SECONDS - elapsed, 0);
  const answered = QUESTIONS.every((_, i) => (answers[i] ?? "").trim().length > 0);

  if (phase === "done") {
    return (
      <div className="mx-auto max-w-xl rounded-xl border border-line bg-surface p-8 text-center">
        <div className="text-3xl font-semibold tracking-tight text-earn">
          +{PAYOUT}
        </div>
        <h2 className="mt-3 text-lg font-semibold">Submitted</h2>
        <p className="mt-2 text-[15px] leading-relaxed text-muted">
          It&rsquo;ll sit in pending while we check it, then move to your
          available balance — usually within a day.
        </p>
        <a
          href="/app/tasks"
          className="mt-6 inline-block rounded-lg bg-earn px-5 py-3 text-sm font-semibold text-white"
        >
          Next study
        </a>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl">
      <div className="rounded-xl border border-line bg-surface p-6">
        <div className="flex items-baseline justify-between gap-4">
          <h2 className="font-semibold tracking-tight">
            Website feedback — home services
          </h2>
          <span className="shrink-0 text-lg font-semibold text-earn">
            {PAYOUT}
          </span>
        </div>

        <ol className="mt-5 space-y-2 text-[15px] text-muted">
          <li>1. Open the website and look around for at least a minute.</li>
          <li>2. Come back here and answer four questions.</li>
          <li>3. Answer honestly — there are no right answers.</li>
        </ol>

        {phase === "brief" && (
          <button
            type="button"
            onClick={begin}
            className="mt-6 w-full rounded-lg bg-earn px-5 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90"
          >
            Open the website
          </button>
        )}

        {phase === "viewing" && (
          <div className="mt-6 rounded-lg border border-line bg-raised p-5 text-center">
            <div className="font-mono text-3xl font-semibold tabular-nums">
              {String(Math.floor(remaining / 60)).padStart(2, "0")}:
              {String(remaining % 60).padStart(2, "0")}
            </div>
            <p className="mt-2 text-sm text-muted">
              Keep looking at the site. The questions unlock when the timer
              runs out.
            </p>
            <div
              className="mt-4 h-1.5 rounded-full bg-surface"
              role="img"
              aria-label={`${Math.round((elapsed / MIN_SECONDS) * 100)}% of minimum time`}
            >
              <div
                className="h-1.5 rounded-r-[4px] bg-earn transition-[width] duration-300"
                style={{
                  width: `${Math.min((elapsed / MIN_SECONDS) * 100, 100)}%`,
                }}
              />
            </div>
          </div>
        )}
      </div>

      {phase === "answering" && (
        <div className="mt-5 space-y-4">
          {QUESTIONS.map((question, i) => (
            <div key={i} className="rounded-xl border border-line bg-surface p-5">
              <label
                htmlFor={`q-${i}`}
                className="block font-medium"
              >
                {question.prompt}
              </label>

              {question.kind === "rating" && (
                <div className="mt-3 flex gap-2">
                  {[1, 2, 3, 4, 5].map((value) => (
                    <button
                      key={value}
                      type="button"
                      aria-pressed={answers[i] === String(value)}
                      onClick={() =>
                        setAnswers((prev) => ({ ...prev, [i]: String(value) }))
                      }
                      className={`h-11 w-11 rounded-lg border text-sm font-semibold transition-colors ${
                        answers[i] === String(value)
                          ? "border-earn bg-earn text-white"
                          : "border-line bg-paper hover:bg-raised"
                      }`}
                    >
                      {value}
                    </button>
                  ))}
                </div>
              )}

              {question.kind === "single" && (
                <div className="mt-3 flex flex-wrap gap-2">
                  {question.options.map((option) => (
                    <button
                      key={option}
                      type="button"
                      aria-pressed={answers[i] === option}
                      onClick={() =>
                        setAnswers((prev) => ({ ...prev, [i]: option }))
                      }
                      className={`rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors ${
                        answers[i] === option
                          ? "border-earn bg-earn text-white"
                          : "border-line bg-paper hover:bg-raised"
                      }`}
                    >
                      {option}
                    </button>
                  ))}
                </div>
              )}

              {(question.kind === "short_text" || question.kind === "long_text") && (
                <textarea
                  id={`q-${i}`}
                  rows={question.kind === "long_text" ? 3 : 2}
                  className={`${field} mt-3`}
                  placeholder="Whatever you actually thought"
                  value={answers[i] ?? ""}
                  onChange={(e) =>
                    setAnswers((prev) => ({ ...prev, [i]: e.target.value }))
                  }
                />
              )}
            </div>
          ))}

          <button
            type="button"
            disabled={!answered}
            onClick={() => setPhase("done")}
            className="w-full rounded-lg bg-earn px-5 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-40"
          >
            Submit and get paid
          </button>
          <p className="text-center text-xs text-faint">
            One-word or copy-pasted answers get rejected. Short and blunt is
            fine.
          </p>
        </div>
      )}
    </div>
  );
}
