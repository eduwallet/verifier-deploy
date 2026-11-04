import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError } from './client';

function respond(status: number, body: unknown) {
  return Promise.resolve(new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }));
}

describe('api client', () => {
  const fetchMock = vi.fn();
  beforeEach(() => vi.stubGlobal('fetch', fetchMock));
  afterEach(() => { fetchMock.mockReset(); vi.unstubAllGlobals(); });

  it('sends the CSRF token of the catalog with state changing calls', async () => {
    fetchMock
      .mockReturnValueOnce(respond(200, { csrfToken: 'tok' }))
      .mockReturnValueOnce(respond(200, { run: 'r' }));
    await api.catalog();
    await api.issue('deferred', { name: 'A' });
    const [url, init] = fetchMock.mock.calls[1];
    expect(url).toBe('/api/issue');
    expect(init.headers['X-CSRF-Token']).toBe('tok');
    expect(JSON.parse(init.body)).toEqual({ credential: 'deferred', data: { name: 'A' } });
  });

  it('refreshes the token once when the session is gone', async () => {
    fetchMock
      .mockReturnValueOnce(respond(200, { csrfToken: 'old' }))
      .mockReturnValueOnce(respond(403, { error: { code: 'csrf', message: 'x' } }))
      .mockReturnValueOnce(respond(200, { csrfToken: 'new' }))
      .mockReturnValueOnce(respond(200, { run: 'r' }));
    await api.catalog();
    await expect(api.issue('deferred', {})).resolves.toEqual({ run: 'r' });
    expect(fetchMock.mock.calls[3][1].headers['X-CSRF-Token']).toBe('new');
  });

  it('turns an error response into an ApiError with the trace', async () => {
    fetchMock.mockReturnValueOnce(respond(502, { error: { code: 'upstream_error', message: 'bad', detail: { status: 500 } }, trace: [{ step: 's' }] }));
    const error = await api.status('r').catch((e) => e);
    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 502, code: 'upstream_error', message: 'bad', detail: { status: 500 } });
    expect(error.trace).toHaveLength(1);
  });

  it('reports an unreachable back-end', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('failed'));
    await expect(api.status('r')).rejects.toMatchObject({ code: 'network', status: 0 });
  });

  it('asks for the trace of a status only when needed', async () => {
    fetchMock.mockReturnValue(respond(200, {}));
    await api.status('r');
    await api.status('r', true);
    expect(fetchMock.mock.calls[0][0]).toBe('/api/session/r');
    expect(fetchMock.mock.calls[1][0]).toBe('/api/session/r?trace=1');
  });
});
