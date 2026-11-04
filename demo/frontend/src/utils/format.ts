/** "in 59 min", "in 2 h 5 min", "expired" */
export function formatRemaining(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined) return '-';
  if (seconds <= 0) return 'expired';
  if (seconds < 60) return `in ${Math.floor(seconds)} s`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `in ${minutes} min`;
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  return rest === 0 ? `in ${hours} h` : `in ${hours} h ${rest} min`;
}

/** "5 s ago", "3 min ago" */
export function formatAgo(timestampMs: number | null | undefined, now = Date.now()): string {
  if (!timestampMs) return '-';
  const seconds = Math.max(0, Math.floor((now - timestampMs) / 1000));
  if (seconds < 60) return `${seconds} s ago`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes} min ago`;
  return `${Math.floor(minutes / 60)} h ago`;
}

/** a duration in seconds as "1 h", "5 min", "30 s" */
export function formatDuration(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined) return '-';
  if (seconds % 3600 === 0 && seconds >= 3600) return `${seconds / 3600} h`;
  if (seconds % 60 === 0 && seconds >= 60) return `${seconds / 60} min`;
  return `${seconds} s`;
}

export function formatDateTime(timestampMs: number | null | undefined): string {
  return timestampMs ? new Date(timestampMs).toLocaleString() : '-';
}

export function formatTime(date: Date): string {
  return date.toLocaleTimeString([], { hour12: false });
}

export function pretty(value: unknown): string {
  return JSON.stringify(value, null, 2) ?? 'undefined';
}
