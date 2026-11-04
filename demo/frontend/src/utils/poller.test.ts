import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPoller } from './poller';

describe('createPoller', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('runs immediately and then at the requested delay', async () => {
    const task = vi.fn().mockResolvedValue(undefined);
    let delay: number | null = 1000;
    const poller = createPoller(task, () => delay);
    poller.start();
    await vi.advanceTimersByTimeAsync(0);
    expect(task).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(1000);
    expect(task).toHaveBeenCalledTimes(2);
    delay = 10_000;
    await vi.advanceTimersByTimeAsync(1000);
    expect(task).toHaveBeenCalledTimes(3);
    await vi.advanceTimersByTimeAsync(9_999);
    expect(task).toHaveBeenCalledTimes(3);
    await vi.advanceTimersByTimeAsync(1);
    expect(task).toHaveBeenCalledTimes(4);
    poller.stop();
  });

  it('stops when the delay is null', async () => {
    const task = vi.fn().mockResolvedValue(undefined);
    const poller = createPoller(task, () => null);
    poller.start();
    await vi.advanceTimersByTimeAsync(5000);
    expect(task).toHaveBeenCalledTimes(1);
    expect(poller.running).toBe(false);
  });

  it('backs off after failures and recovers', async () => {
    const task = vi.fn().mockRejectedValueOnce(new Error('x')).mockRejectedValueOnce(new Error('x')).mockResolvedValue(undefined);
    const onError = vi.fn();
    const poller = createPoller(task, () => 1000, { onError });
    poller.start();
    await vi.advanceTimersByTimeAsync(0);
    expect(task).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(1000); // first retry after 1 s
    expect(task).toHaveBeenCalledTimes(2);
    await vi.advanceTimersByTimeAsync(1999); // second retry after 2 s
    expect(task).toHaveBeenCalledTimes(2);
    await vi.advanceTimersByTimeAsync(1);
    expect(task).toHaveBeenCalledTimes(3);
    expect(onError).toHaveBeenCalledTimes(2);
    await vi.advanceTimersByTimeAsync(1000); // back to the normal delay
    expect(task).toHaveBeenCalledTimes(4);
    poller.stop();
  });

  it('does not start twice and stop cancels the pending run', async () => {
    const task = vi.fn().mockResolvedValue(undefined);
    const poller = createPoller(task, () => 1000);
    poller.start();
    poller.start();
    await vi.advanceTimersByTimeAsync(0);
    expect(task).toHaveBeenCalledTimes(1);
    poller.stop();
    await vi.advanceTimersByTimeAsync(5000);
    expect(task).toHaveBeenCalledTimes(1);
  });
});
