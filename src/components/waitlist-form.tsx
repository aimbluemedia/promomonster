"use client";

import { useState, type FormEvent } from "react";

type Props = {
  role: "business" | "panelist";
  source?: string;
  cta?: string;
  note?: string;
};

export function WaitlistForm({ role, source, cta, note }: Props) {
  const [state, setState] = useState<"idle" | "saving" | "done" | "error">(
    "idle",
  );
  const [message, setMessage] = useState("");

  const isBusiness = role === "business";
  const accent = isBusiness ? "bg-brand" : "bg-earn";

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setState("saving");

    const form = new FormData(event.currentTarget);
    const body = {
      role,
      source,
      email: String(form.get("email") ?? ""),
      name: String(form.get("name") ?? "") || undefined,
      company: String(form.get("company") ?? "") || undefined,
      website: String(form.get("website") ?? "") || undefined,
      goal: String(form.get("goal") ?? "") || undefined,
      region: String(form.get("region") ?? "") || undefined,
      postalCode: String(form.get("postalCode") ?? "") || undefined,
      country: isBusiness ? undefined : "US",
      website_url: String(form.get("website_url") ?? ""),
    };

    try {
      const response = await fetch("/api/waitlist", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const result = await response.json().catch(() => ({}));

      if (!response.ok) {
        setState("error");
        setMessage(result.error ?? "Something went wrong. Please try again.");
        return;
      }
      setState("done");
    } catch {
      setState("error");
      setMessage("Couldn't reach the server. Please try again.");
    }
  }

  if (state === "done") {
    return (
      <div className="rounded-xl border border-line bg-surface p-6">
        <p className="text-lg font-semibold">You&rsquo;re on the list.</p>
        <p className="mt-2 text-sm text-muted">
          {isBusiness
            ? "We'll email you within a day or two to set up your first study. Early studies are run hands-on, so you'll be talking to a person, not a form."
            : "We open spots in batches so there's always work available when you log in. You'll get an email as soon as yours is ready."}
        </p>
      </div>
    );
  }

  const field =
    "w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-brand focus:outline-2 focus:outline-offset-1 focus:outline-brand";

  return (
    <form onSubmit={onSubmit} className="space-y-3">
      <div aria-hidden="true" className="absolute h-0 w-0 overflow-hidden">
        <label htmlFor={`website_url-${role}`}>Leave this field empty</label>
        <input
          id={`website_url-${role}`}
          type="text"
          name="website_url"
          tabIndex={-1}
          autoComplete="off"
        />
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <div>
          <label htmlFor={`name-${role}`} className="sr-only">
            Name
          </label>
          <input
            id={`name-${role}`}
            name="name"
            className={field}
            placeholder="Your name"
            autoComplete="name"
          />
        </div>
        <div>
          <label htmlFor={`email-${role}`} className="sr-only">
            Email
          </label>
          <input
            id={`email-${role}`}
            name="email"
            type="email"
            required
            className={field}
            placeholder="you@example.com"
            autoComplete="email"
          />
        </div>
      </div>

      {isBusiness ? (
        <>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label htmlFor="company" className="sr-only">
                Company
              </label>
              <input
                id="company"
                name="company"
                className={field}
                placeholder="Company"
                autoComplete="organization"
              />
            </div>
            <div>
              <label htmlFor="website" className="sr-only">
                Website
              </label>
              <input
                id="website"
                name="website"
                className={field}
                placeholder="yoursite.com"
                autoComplete="url"
              />
            </div>
          </div>
          <div>
            <label htmlFor="goal" className="sr-only">
              What do you want to find out?
            </label>
            <textarea
              id="goal"
              name="goal"
              rows={3}
              className={field}
              placeholder="What do you want to find out? (e.g. why visitors leave my pricing page)"
            />
          </div>
        </>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <label htmlFor="region" className="sr-only">
              State
            </label>
            <input
              id="region"
              name="region"
              className={field}
              placeholder="State"
              autoComplete="address-level1"
            />
          </div>
          <div>
            <label htmlFor="postalCode" className="sr-only">
              ZIP code
            </label>
            <input
              id="postalCode"
              name="postalCode"
              className={field}
              placeholder="ZIP code"
              autoComplete="postal-code"
              inputMode="numeric"
            />
          </div>
        </div>
      )}

      <button
        type="submit"
        disabled={state === "saving"}
        className={`w-full rounded-lg ${accent} px-5 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-60`}
      >
        {state === "saving"
          ? "One moment…"
          : (cta ?? (isBusiness ? "Request a study" : "Join the panel"))}
      </button>

      {state === "error" && (
        <p role="alert" className="text-sm text-earn">
          {message}
        </p>
      )}
      {note && <p className="text-xs text-faint">{note}</p>}
    </form>
  );
}
