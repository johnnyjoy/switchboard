import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { api } from './api';

describe('api', () => {
  const originalFetch = globalThis.fetch;

  beforeEach(() => {
    globalThis.fetch = vi.fn();
  });

  afterEach(() => {
    globalThis.fetch = originalFetch;
  });

  it('getApps returns parsed apps array', async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => [
        { id: 'a1', slug: 'app1', name: 'App 1', description: null, app_path: null, enabled: true, created_at: '', updated_at: '' },
      ],
    });
    const apps = await api.getApps();
    expect(apps).toHaveLength(1);
    expect(apps[0].slug).toBe('app1');
    expect(apps[0].name).toBe('App 1');
  });

  it('getApps throws on non-ok response', async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: false,
      status: 500,
      statusText: 'Internal Server Error',
      json: async () => ({ error: 'Server error' }),
    });
    await expect(api.getApps()).rejects.toThrow('Server error');
  });

  it('getApps throws friendly message when fetch fails (e.g. ECONNREFUSED)', async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockRejectedValueOnce(new Error('fetch failed'));
    await expect(api.getApps()).rejects.toThrow('Backend unreachable');
  });

  it('getApps throws friendly message on 502', async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: false,
      status: 502,
      json: async () => ({}),
    });
    await expect(api.getApps()).rejects.toThrow('Backend unreachable');
  });

  it('getEndpoints with appId appends query', async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => [],
    });
    await api.getEndpoints('app-123');
    expect(globalThis.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/endpoints?app_id=app-123'),
      expect.any(Object)
    );
  });

  it('copyApp POSTs to app copy endpoint', async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: true,
      status: 201,
      json: async () => ({ id: 'a2', slug: 'app-copy', name: 'App Copy', description: null, app_path: null, enabled: true, created_at: '', updated_at: '' }),
    });
    const copied = await api.copyApp('a1');
    expect(copied.slug).toBe('app-copy');
    expect(globalThis.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/apps/a1/copy'),
      expect.objectContaining({ method: 'POST' })
    );
  });

  it('deleteApp DELETEs app endpoint', async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: true,
      status: 204,
      json: async () => ({}),
    });
    await api.deleteApp('a1');
    expect(globalThis.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/apps/a1'),
      expect.objectContaining({ method: 'DELETE' })
    );
  });

  it('copyEndpoint POSTs to endpoint copy endpoint', async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: true,
      status: 201,
      json: async () => ({ id: 'e2', app_id: 'a1', name: 'Health Copy', host: null, path: '/health-copy', methods: ['GET'], handler_class: 'App\\Health', handler_method: 'handle', enabled: false, created_at: '', updated_at: '' }),
    });
    const copied = await api.copyEndpoint('e1');
    expect(copied.path).toBe('/health-copy');
    expect(globalThis.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/endpoints/e1/copy'),
      expect.objectContaining({ method: 'POST' })
    );
  });

  it('testRequest POSTs body and returns TestResult shape', async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        matched: true,
        endpoint: { id: 'e1', name: 'Health', path: '/health', methods: ['GET'], handler_class: 'App\\Health', handler_method: 'handle' },
        app: { id: 'a1', name: 'App', slug: 'app' },
        runtimeAvailable: true,
        runtimeResponse: { ok: true },
      }),
    });
    const result = await api.testRequest({ path: '/health', method: 'GET' });
    expect(result.matched).toBe(true);
    expect(result.endpoint?.path).toBe('/health');
    expect(result.app?.slug).toBe('app');
  });
});
