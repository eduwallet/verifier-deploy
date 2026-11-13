import type { IssuedItem, Phase, StatusResponse, TransactionItem } from '@/api/types';

export type Step = 1 | 2 | 3 | 4;

export const STEPS: { step: Step; title: string }[] = [
  { step: 1, title: 'Prepare' },
  { step: 2, title: 'Offer' },
  { step: 3, title: 'Release' },
  { step: 4, title: 'Completed' },
];

/** What the visitor sees per credential of the offer */
export type CredentialState = 'waiting' | 'pending' | 'ready' | 'issued' | 'denied';

export const STATE_LABELS: Record<CredentialState, string> = {
  waiting: 'Waiting for the wallet',
  pending: 'Waiting for release',
  ready: 'Released, waiting for the wallet',
  issued: 'Issued',
  denied: 'Denied',
};

export type Severity = 'secondary' | 'info' | 'success' | 'warn' | 'danger';

export function stateSeverity(state: CredentialState): Severity {
  switch (state) {
    case 'issued': return 'success';
    case 'denied': return 'danger';
    case 'pending':
    case 'ready': return 'info';
    default: return 'secondary';
  }
}

/**
 * The state of the credential with this catalog key. A credential is issued when the issuer lists it as
 * issued; the deferred credential has a transaction of its own.
 */
export function credentialState(key: string, status: StatusResponse | null): CredentialState {
  if (status?.issued.some((i: IssuedItem) => i.key === key)) {
    return 'issued';
  }
  const transaction = status?.deferred.find((t: TransactionItem) => t.key === key);
  switch (transaction?.status) {
    case 'CREDENTIAL_PENDING': return 'pending';
    case 'CREDENTIAL_READY': return 'ready';
    case 'CREDENTIAL_DENIED': return 'denied';
    case 'CREDENTIAL_ISSUED': return 'issued';
    default: return 'waiting';
  }
}

/**
 * The wizard step for the latest status of the offer. The offer holds credentials that are issued at once
 * and one that waits for the back-office, so the order in which the wallet requests them is not fixed:
 * the step follows the deferred transaction and the end of the issuance, and not the session status.
 */
export function stepForStatus(status: StatusResponse | null): Step {
  if (!status) return 1;
  if (status.status === 'CREDENTIAL_ISSUED' || status.status === 'CREDENTIAL_DENIED') return 4;
  if (status.deferred.some((t) => t.status === 'CREDENTIAL_DENIED')) return 4;
  return status.deferred.length ? 3 : 2;
}

/** The phase to show for the offer as a whole */
export function overallPhase(status: StatusResponse | null, hasRun: boolean): Phase | null {
  if (!status) return hasRun ? 'created' : null;
  if (status.deferred.some((t) => t.status === 'CREDENTIAL_DENIED')) return 'denied';
  if (status.phase === 'expired' || status.phase === 'error') return status.phase;
  if (status.deferred.some((t) => t.status === 'CREDENTIAL_PENDING')) return 'pending';
  if (status.deferred.some((t) => t.status === 'CREDENTIAL_READY')) return 'ready';
  return status.phase;
}
