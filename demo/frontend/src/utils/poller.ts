export interface Poller {
  start(): void;
  stop(): void;
  readonly running: boolean;
}

/**
 * Runs `task` repeatedly, never overlapping. After a success the next run is scheduled `nextDelay()`
 * milliseconds later (null stops the poller); after a failure the delay doubles up to `maxBackoffMs`.
 */
export function createPoller(
  task: () => Promise<void>,
  nextDelay: () => number | null,
  options: { immediate?: boolean; maxBackoffMs?: number; onError?: (e: unknown) => void } = {},
): Poller {
  const { immediate = true, maxBackoffMs = 10_000, onError } = options;
  let timer: ReturnType<typeof setTimeout> | null = null;
  let running = false;
  let failures = 0;

  const schedule = (delay: number | null) => {
    if (!running) return;
    if (delay === null) {
      running = false;
      return;
    }
    timer = setTimeout(run, delay);
  };

  const run = async () => {
    timer = null;
    try {
      await task();
      failures = 0;
      schedule(nextDelay());
    } catch (e) {
      failures++;
      onError?.(e);
      schedule(Math.min(1000 * 2 ** (failures - 1), maxBackoffMs));
    }
  };

  return {
    start() {
      if (running) return;
      running = true;
      failures = 0;
      if (immediate) {
        void run();
      } else {
        schedule(nextDelay());
      }
    },
    stop() {
      running = false;
      if (timer !== null) {
        clearTimeout(timer);
        timer = null;
      }
    },
    get running() {
      return running;
    },
  };
}
