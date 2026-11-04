import type {
  AuthorizeResponse, Catalog, DeferredListResponse, DenyResponse,
  IssueResponse, OfferResponse, StatusResponse, TraceEntry,
} from './types';

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly detail?: unknown,
    public readonly trace: TraceEntry[] = [],
  ) {
    super(message);
  }
}

const BASE = '/api';
let csrfToken: string | null = null;

async function request<T>(method: 'GET' | 'POST', path: string, body?: unknown, retry = true): Promise<T> {
  if (method !== 'GET' && csrfToken === null) {
    await api.catalog();
  }
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  if (method !== 'GET' && csrfToken !== null) {
    headers['X-CSRF-Token'] = csrfToken;
  }

  let response: Response;
  try {
    response = await fetch(BASE + path, {
      method,
      headers,
      credentials: 'same-origin',
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new ApiError(0, 'network', 'The back-end could not be reached');
  }

  let json: unknown = null;
  try {
    json = await response.json();
  } catch {
    // not JSON, handled below
  }
  if (response.ok) {
    return json as T;
  }

  const error = (json as { error?: { code?: string; message?: string; detail?: unknown }; trace?: TraceEntry[] } | null);
  // the session (and with it the token) can be gone, for instance after the browser was restarted
  if (response.status === 403 && error?.error?.code === 'csrf' && retry) {
    csrfToken = null;
    await api.catalog();
    return request(method, path, body, false);
  }
  throw new ApiError(
    response.status,
    error?.error?.code ?? 'http_error',
    error?.error?.message ?? `The back-end answered with status ${response.status}`,
    error?.error?.detail,
    error?.trace ?? [],
  );
}

export const api = {
  async catalog(): Promise<Catalog> {
    const catalog = await request<Catalog>('GET', '/catalog');
    csrfToken = catalog.csrfToken;
    return catalog;
  },
  issue(credential: string, data: Record<string, string>) {
    return request<IssueResponse>('POST', '/issue', { credential, data });
  },
  status(run: string, trace = false) {
    return request<StatusResponse>('GET', `/session/${run}${trace ? '?trace=1' : ''}`);
  },
  offer(run: string) {
    return request<OfferResponse>('GET', `/session/${run}/offer`);
  },
  deferred(run: string) {
    return request<DeferredListResponse>('GET', `/session/${run}/deferred`);
  },
  authorize(run: string, claims: Record<string, string>) {
    return request<AuthorizeResponse>('POST', `/session/${run}/deferred/authorize`, { claims });
  },
  deny(run: string, reason?: string) {
    return request<DenyResponse>('POST', `/session/${run}/deferred/deny`, reason ? { reason } : {});
  },
};
