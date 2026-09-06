export function Logo({ className = "" }: { className?: string }) {
  return (
    <span className={`inline-flex items-center gap-2 ${className}`}>
      <svg
        width="26"
        height="26"
        viewBox="0 0 32 32"
        fill="none"
        aria-hidden="true"
        className="shrink-0"
      >
        <rect width="32" height="32" rx="8" className="fill-brand" />
        <circle cx="16" cy="15" r="7.5" className="fill-paper" />
        <circle cx="16" cy="15" r="3.4" className="fill-ink" />
        <path
          d="M7 25.5c2.2-1.6 4.3-1.6 6 0 1.7 1.6 3.8 1.6 6 0 1.7-1.6 3.8-1.6 6 0"
          className="stroke-lime"
          strokeWidth="2.4"
          strokeLinecap="round"
        />
      </svg>
      <span className="text-[17px] font-semibold tracking-tight">
        Promo<span className="text-brand">Monster</span>
      </span>
    </span>
  );
}
