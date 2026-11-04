import { describe, expect, it } from 'vitest';
import { formatAgo, formatDuration, formatRemaining } from './format';

describe('format', () => {
  it('formats the remaining time', () => {
    expect(formatRemaining(null)).toBe('-');
    expect(formatRemaining(-3)).toBe('expired');
    expect(formatRemaining(42)).toBe('in 42 s');
    expect(formatRemaining(59 * 60)).toBe('in 59 min');
    expect(formatRemaining(2 * 3600 + 5 * 60)).toBe('in 2 h 5 min');
    expect(formatRemaining(3 * 3600)).toBe('in 3 h');
  });

  it('formats durations', () => {
    expect(formatDuration(3600)).toBe('1 h');
    expect(formatDuration(300)).toBe('5 min');
    expect(formatDuration(5)).toBe('5 s');
    expect(formatDuration(90)).toBe('90 s');
  });

  it('formats the time since', () => {
    expect(formatAgo(10_000, 15_000)).toBe('5 s ago');
    expect(formatAgo(0)).toBe('-');
    expect(formatAgo(1000, 1000 + 3 * 60_000)).toBe('3 min ago');
  });
});
