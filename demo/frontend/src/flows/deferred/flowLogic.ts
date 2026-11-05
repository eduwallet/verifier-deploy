import type { Phase } from '@/api/types';

export type Step = 1 | 2 | 3 | 4 | 5 | 6;

export const STEPS: { step: Step; title: string }[] = [
  { step: 1, title: 'Prepare' },
  { step: 2, title: 'Offer' },
  { step: 3, title: 'Wallet request' },
  { step: 4, title: 'Back-office' },
  { step: 5, title: 'Collection' },
  { step: 6, title: 'Completed' },
];

/**
 * The wizard step that belongs to the latest known phase of the offer. `null` is: no offer yet.
 * Step 2 lasts until the offer is no longer 'created' or 'retrieved' (the wallet accepted),
 * step 3 until the wallet's credential request is pending, step 5 until the wallet has collected
 * the released credential (or it was denied).
 */
export function stepForPhase(phase: Phase | null): Step {
  switch (phase) {
    case null: return 1;
    case 'created':
    case 'retrieved': return 2;
    case 'token':
    case 'processing': return 3;
    case 'pending': return 4;
    case 'ready': return 5;
    case 'denied':
    case 'issued': return 6;
    default: return 2; // 'expired' and 'error' keep the step the visitor is in
  }
}

/** Delay before the next status poll, or null when polling should stop */
export function pollDelayMs(phase: Phase | null): number | null {
  switch (phase) {
    case 'denied':
    case 'expired':
    case 'error': return null;
    case 'issued': return 10_000; // only the wallet's notification can still arrive
    default: return 1_000;
  }
}

export const PHASE_LABELS: Record<Phase, string> = {
  created: 'Offer created',
  retrieved: 'Offer retrieved by wallet',
  token: 'Access token issued',
  processing: 'Processing',
  pending: 'Pending back-office',
  ready: 'Ready to collect',
  denied: 'Denied',
  issued: 'Credential issued',
  expired: 'Expired',
  error: 'Error',
};

export type Severity = 'secondary' | 'info' | 'success' | 'warn' | 'danger';

export function phaseSeverity(phase: Phase | null): Severity {
  switch (phase) {
    case 'issued': return 'success';
    case 'denied':
    case 'error': return 'danger';
    case 'expired': return 'warn';
    case 'pending':
    case 'ready': return 'info';
    default: return 'secondary';
  }
}

/** What the timeline says when the issuer reports a status */
export const STATUS_EVENTS: Record<string, string> = {
  OFFER_CREATED: 'Offer created at the issuer',
  OFFER_URI_RETRIEVED: 'Wallet retrieved the credential offer',
  ACCESS_TOKEN_CREATED: 'Wallet received an access token',
  CREDENTIAL_PENDING: 'Wallet requested the credential, the issuer answered 202 (deferred)',
  CREDENTIAL_READY: 'Back-office released the credential, waiting for the wallet to collect it',
  CREDENTIAL_ISSUED: 'Wallet collected the credential',
  CREDENTIAL_DENIED: 'Credential denied by the back-office',
};

/** Claim names the back-office can supply: single-level paths of the supply lists */
export function suppliedClaimNames(claims: { supply: string[][] }[]): string[] {
  const names: string[] = [];
  for (const claim of claims) {
    for (const path of claim.supply) {
      if (path.length === 1 && !names.includes(path[0])) {
        names.push(path[0]);
      }
    }
  }
  return names;
}
