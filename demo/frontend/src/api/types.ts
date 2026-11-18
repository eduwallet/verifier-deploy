// The JSON the PHP back-end returns (see demo/app/src/App.php).

/** Normalised phase of an offer; the raw issuer status is available as `status` */
export type Phase =
  | 'created' | 'retrieved' | 'presentation' | 'verified' | 'token' | 'processing' | 'pending'
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

export interface CatalogBundle {
  title: string;
  issuer: string;
  /** the catalog keys of the credentials, in the order of the offer */
  credentials: string[];
}

/** Issuance during which the wallet first presents credentials */
export interface CatalogPresentation {
  title: string;
  issuer: string;
  /** the catalog key of the credential that can be issued first, if the wallet has none */
  prerequisite: string | null;
  /** credential claim -> claim of the presented credential that fills it */
  mapping: Record<string, string>;
  /** the claims the wallet is asked to disclose */
  requested: string[];
}

export interface Catalog {
  csrfToken: string;
  environment: string;
  issuers: Record<string, { name: string; configured: boolean }>;
  credentials: Record<string, CatalogCredential>;
  bundles: Record<string, CatalogBundle>;
  presentations?: Record<string, CatalogPresentation>;
}

export interface IssueResponse extends Traced {
  run: string;
  offerId: string;
  offerUri: string;
  txCode: string | null;
  data: Record<string, string>;
}

/** A credential of an offer that the issuer issued */
export interface IssuedItem {
  /** the catalog key of the credential */
  key: string;
  uuid: string;
}

/** A deferred transaction of an offer */
export interface TransactionItem {
  id: string;
  /** the catalog key of the credential */
  key: string;
  status: string;
  expiresAt: number | null;
  reason: string | null;
}

export interface IssueMultipleResponse extends Omit<IssueResponse, 'data'> {
  data: Record<string, Record<string, string>>;
}

export interface ReleaseResponse extends Traced {
  released: boolean;
  claims: Record<string, string>;
  result: unknown;
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
  issued: IssuedItem[];
  deferred: TransactionItem[];
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
