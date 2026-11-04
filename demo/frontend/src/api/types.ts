// The JSON the PHP back-end returns (see demo/app/src/App.php).

/** Normalised phase of an offer; the raw issuer status is available as `status` */
export type Phase =
  | 'created' | 'retrieved' | 'token' | 'processing' | 'pending'
  | 'ready' | 'denied' | 'issued' | 'expired' | 'error';

export interface TraceEntry {
  step: string;
  at: string;
  method: string;
  url: string;
  requestHeaders: Record<string, string>;
  requestBody: unknown;
  status: number;
  responseHeaders: Record<string, string>;
  responseBody: unknown;
  durationMs: number;
  error: string | null;
}

export interface Traced {
  trace?: TraceEntry[];
}

export interface CatalogField {
  name: string;
  label: string;
  default: string;
  maxLength: number;
}

export interface CatalogCredential {
  title: string;
  issuer: string;
  deferred: boolean;
  fields: CatalogField[];
}

export interface Catalog {
  csrfToken: string;
  environment: string;
  issuers: Record<string, { name: string; configured: boolean }>;
  credentials: Record<string, CatalogCredential>;
}

export interface IssueResponse extends Traced {
  run: string;
  offerId: string;
  offerUri: string;
  txCode: string | null;
  data: Record<string, string>;
}

export interface StatusResponse extends Traced {
  run: string;
  status: string;
  phase: Phase;
  createdAt: number | null;
  lastUpdatedAt: number | null;
  expiresAt: number | null;
  reason: string | null;
  credentialUuid: string | null;
  /** request/response data per protocol phase, in protocol order */
  requests: Record<string, unknown>;
  notification: unknown;
}

export interface OfferResponse extends Traced {
  offerUri: string;
  offer: Record<string, unknown>;
}

export interface DeferredClaimDefinition {
  path: string[];
  type: string;
  mandatory: boolean;
  origin: string[][];
  supply: string[][];
}

export interface DeferredDetail {
  id: string;
  status: string;
  createdAt: number;
  expiresAt: number;
  expiresInSeconds: number | null;
  credential_configuration_id: string;
  format: string | null;
  proofs: number;
  ttl: number;
  interval: number;
  data: Record<string, unknown>;
  claims_supplied: Record<string, unknown>;
  claims: DeferredClaimDefinition[];
  reason?: string;
}

export interface DeferredItem {
  id: string;
  shortId: string;
  credential: string | null;
  status: string | null;
  createdAt: number | null;
  expiresAt: number | null;
  expiresInSeconds: number | null;
  claimsKnown: string[];
  proofs: number;
  own: boolean;
}

export interface DeferredListResponse extends Traced {
  items: DeferredItem[];
  own: DeferredDetail | null;
}

export interface AuthorizeResponse extends Traced {
  authorized: boolean;
  claims: Record<string, string>;
  result: unknown;
}

export interface DenyResponse extends Traced {
  denied: boolean;
  result: unknown;
}
