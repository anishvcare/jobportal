/** A slim determinate progress bar driven by a 0-100 percent value. */
export function Progress({
  percent,
  label,
  className = "",
}: {
  percent: number;
  label?: string;
  className?: string;
}) {
  const value = Math.min(100, Math.max(0, Math.round(percent)));

  return (
    <div className={className}>
      <div
        className="h-2 w-full overflow-hidden rounded-full bg-slate-200"
        role="progressbar"
        aria-valuenow={value}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={label ?? "Upload progress"}
      >
        <div
          className="h-full rounded-full bg-brand-600 transition-all"
          style={{ width: `${value}%` }}
        />
      </div>
      <p className="mt-1 text-xs text-slate-500">{label ?? `Uploading… ${value}%`}</p>
    </div>
  );
}
