"use client";

import { useId, useState } from "react";

export type Bucket = { label: string; count: number };

/**
 * Horizontal distribution bars for one survey question.
 *
 * One series, so no legend — the question is the title. Every bar is directly
 * labelled with its count and share, which doubles as the table view and keeps
 * identity off colour alone.
 *
 * `scale: "ordinal"` paints a validated light→dark single-hue ramp for ordered
 * answers (a 1–5 rating), where colour should carry the order. `scale: "flat"`
 * paints every bar the same hue for unordered options — colouring nominal bars
 * by value would re-encode what bar length already shows.
 */
export function Distribution({
  buckets,
  scale = "flat",
}: {
  buckets: Bucket[];
  scale?: "flat" | "ordinal";
}) {
  const [hovered, setHovered] = useState<number | null>(null);
  const id = useId();

  const total = buckets.reduce((sum, b) => sum + b.count, 0) || 1;
  const max = Math.max(...buckets.map((b) => b.count), 1);

  const ordinalFills = [
    "bg-scale-1",
    "bg-scale-2",
    "bg-scale-3",
    "bg-scale-4",
    "bg-scale-5",
  ];

  return (
    <ul className="space-y-2.5">
      {buckets.map((bucket, i) => {
        const share = Math.round((bucket.count / total) * 100);
        const width = Math.max((bucket.count / max) * 100, bucket.count > 0 ? 1.5 : 0);
        const fill =
          scale === "ordinal"
            ? (ordinalFills[i] ?? ordinalFills[ordinalFills.length - 1])
            : "bg-brand";

        return (
          <li
            key={`${id}-${bucket.label}`}
            className="grid grid-cols-[minmax(0,9rem)_1fr_auto] items-center gap-3 text-sm"
            onMouseEnter={() => setHovered(i)}
            onMouseLeave={() => setHovered(null)}
          >
            <span className="truncate text-muted" title={bucket.label}>
              {bucket.label}
            </span>

            <span className="relative block h-5 rounded-sm bg-raised">
              <span
                className={`absolute inset-y-0 left-0 rounded-r-[4px] ${fill} transition-[width] duration-300`}
                style={{ width: `${width}%` }}
              />
              {hovered === i && (
                <span
                  role="status"
                  className="pointer-events-none absolute -top-8 left-2 z-10 whitespace-nowrap rounded-md border border-line bg-surface px-2 py-1 text-xs text-ink shadow-sm"
                >
                  {bucket.label}: {bucket.count} of {total}
                </span>
              )}
            </span>

            <span className="whitespace-nowrap font-mono text-xs text-muted">
              {bucket.count}
              <span className="text-faint"> · {share}%</span>
            </span>
          </li>
        );
      })}
    </ul>
  );
}
