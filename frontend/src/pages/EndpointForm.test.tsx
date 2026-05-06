import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import EndpointForm from './EndpointForm';
import { api } from '../api';

vi.mock('../api', () => ({
  api: {
    getApps: vi.fn(),
    getEndpoints: vi.fn(),
    getPredicates: vi.fn(),
    createEndpoint: vi.fn(),
    updateEndpoint: vi.fn(),
    createPredicate: vi.fn(),
    updatePredicate: vi.fn(),
    deletePredicate: vi.fn(),
  },
}));

function renderCreateForm() {
  return render(
    <MemoryRouter initialEntries={['/apps/app-1/endpoints/new']} future={{ v7_relativeSplatPath: true, v7_startTransition: true }}>
      <Routes>
        <Route path="/apps/:appId/endpoints/new" element={<EndpointForm />} />
        <Route path="/endpoints/:endpointId" element={<div>Endpoint detail</div>} />
      </Routes>
    </MemoryRouter>
  );
}

describe('EndpointForm', () => {
  beforeEach(() => {
    vi.mocked(api.getApps).mockReset();
    vi.mocked(api.createEndpoint).mockReset();
    vi.mocked(api.createPredicate).mockReset();
    vi.mocked(api.getApps).mockResolvedValue([
      {
        id: 'app-1',
        slug: 'minimal',
        name: 'Minimal',
        description: null,
        app_path: null,
        enabled: true,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
      },
    ]);
  });

  it('creates an endpoint with normalized path and class method dispatch target', async () => {
    vi.mocked(api.createEndpoint).mockResolvedValue({
      id: 'endpoint-1',
      app_id: 'app-1',
      name: 'Health',
      host: null,
      path: '/health',
      methods: ['GET'],
      handler_class: 'Minimal\\Health', handler_method: 'handle',
      enabled: true,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    });

    renderCreateForm();

    await screen.findByRole('heading', { name: /create endpoint/i });
    fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'Health' } });
    fireEvent.change(screen.getByLabelText(/path/i), { target: { value: 'health' } });
    fireEvent.change(screen.getByLabelText(/php class/i), { target: { value: 'Minimal\\Health' } });
    fireEvent.change(screen.getByLabelText(/method/i), { target: { value: 'handle' } });
    fireEvent.click(screen.getByRole('button', { name: /^create$/i }));

    await waitFor(() => {
      expect(api.createEndpoint).toHaveBeenCalledWith(
        expect.objectContaining({
          app_id: 'app-1',
          name: 'Health',
          path: '/health',
          methods: ['GET'],
          handler_class: 'Minimal\\Health', handler_method: 'handle',
          enabled: true,
        })
      );
    });
    expect(await screen.findByText('Endpoint detail')).toBeInTheDocument();
  });

  it('shows endpoint predicate guidance in the create workflow', async () => {
    renderCreateForm();

    expect(await screen.findByText(/predicates are match-time gates/i)).toBeInTheDocument();
    expect(screen.getByText(/app-owned php class/i)).toBeInTheDocument();
    expect(screen.getByText(/use handle by default/i)).toBeInTheDocument();
  });
});
