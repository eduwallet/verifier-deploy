import type { Phase } from '@/api/types';

export type Step = 1 | 2 | 3 | 4;

export const STEPS: { step: Step; title: string }[] = [
  { step: 1, title: 'eduID' },
  { step: 2, title: 'Offer' },
  { step: 3, title: 'Presentation' },
  { step: 4, title: 'Completed' },
];

/**
 * The wizard step for the phase of the issuance offer (not of the optional eduID offer). The wallet accepts the
 * offer in step 2, presents the eduID in step 3 and receives the credential in step 4. A rejected presentation, an
 * expired offer and an error stay in the step where they happened; the presentation is rejected in step 3.
 * `null` means that there is no offer yet.
 */
export function stepForPhase(phase: Phase | null): Step | null {
  switch (phase) {
    case null: return null;
    case 'created':
    case 'retrieved': return 2;
    case 'presentation':
    case 'verified':
    case 'token':
    case 'processing':
    case 'denied': return 3;
    case 'issued': return 4;
    default: return null; // 'expired' and 'error' keep the step the visitor is in
  }
}

/** The milestones of the issuance, in order */
export type Milestone = 'retrieved' | 'presentation' | 'verified' | 'token' | 'issued';

export const MILESTONES: { id: Milestone; label: string }[] = [
  { id: 'retrieved', label: 'The wallet retrieved the credential offer' },
  { id: 'presentation', label: 'The issuer asked for an eduID presentation' },
  { id: 'verified', label: 'The verifier accepted the presentation' },
  { id: 'token', label: 'The wallet received an access token' },
  { id: 'issued', label: 'The wallet received the credential' },
];

/** The raw issuer status as far as it is in the order of the authorization code flow with a presentation */
const RANK: Record<string, number> = {
  OFFER_CREATED: 0,
  OFFER_URI_RETRIEVED: 1,
  PRESENTATION_REQUESTED: 2,
  PRESENTATION_VERIFIED: 3,
  ACCESS_TOKEN_CREATED: 4,
  CREDENTIAL_REQUEST_RECEIVED: 4,
  CREDENTIAL_ISSUED: 5,
};

/** Whether the issuance has reached the milestone, given the raw status of the offer */
export function reached(milestone: Milestone, status: string | null | undefined): boolean {
  if (!status || !(status in RANK)) return false;
  const at = MILESTONES.findIndex((m) => m.id === milestone) + 1;
  return RANK[status] >= at;
}

/** Delay before the next poll of the issuance offer, or null to stop */
export function pollDelayMs(phase: Phase | null): number | null {
  switch (phase) {
    case 'denied':
    case 'expired':
    case 'error': return null;
    case 'issued': return 10_000; // only the wallet's notification can still arrive
    default: return 1_000;
  }
}

/** Delay before the next poll of the optional eduID offer: it stops when the eduID is issued */
export function eduidPollDelayMs(phase: Phase | null): number | null {
  return phase === 'issued' ? null : pollDelayMs(phase);
}
