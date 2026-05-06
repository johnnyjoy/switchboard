import { describe, it, expect, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import AppDetail from './AppDetail';
import { api } from '../api';

vi.mock('../api', () => ({
  api: {
    getApps: vi.fn(),
    getEndpoints: vi.fn(),
    updateEndpoint: vi.fn(),
    copyApp: vi.fn(),
    deleteApp: vi.fn(),
    copyEndpoint: vi.fn(),
    deleteEndpoint: vi.fn(),
  },
}));

const app = { id: 'a1', slug: 'minimal', name: 'Minimal', description: 'Minimal app', app_path: null, enabled: true, created_at: '', updated_at: '' };
const endpoint = { id: 'e1', app_id: 'a1', name: 'Health', host: null, path: '/health', methods: ['GET'], handler_class: 'Minimal\\Health', handler_method: 'handle', enabled: true, created_at: '', updated_at: '' };

function renderDetail() {
  return render(
    <MemoryRouter initialEntries={['/apps/a1']} future={{ v7_relativeSplatPath: true, v7_startTransition: true }}>
      <Routes>
        <Route path="/" element={<div>Apps</div>} />
        <Route path="/apps/:appId" element={<AppDetail />} />
        <Route path="/endpoints/:endpointId" element={<div>Endpoint detail</div>} />
      </Routes>
    </MemoryRouter>
  );
}

describe('AppDetail', () => {
  beforeEach(() => {
    vi.mocked(api.getApps).mockReset();
    vi.mocked(api.getEndpoints).mockReset();
    vi.mocked(api.updateEndpoint).mockReset();
    vi.mocked(api.copyApp).mockReset();
    vi.mocked(api.deleteApp).mockReset();
    vi.mocked(api.copyEndpoint).mockReset();
    vi.mocked(api.deleteEndpoint).mockReset();
    vi.mocked(api.getApps).mockResolvedValue([app]);
    vi.mocked(api.getEndpoints).mockResolvedValue([endpoint]);
    vi.mocked(api.updateEndpoint).mockResolvedValue(endpoint);
    vi.spyOn(window, 'confirm').mockReset();
  });

  it('renders compact app and endpoint lifecycle actions with endpoint status labels', async () => {
    renderDetail();

    expect(await screen.findByRole('heading', { name: 'Minimal' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /copy minimal/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /delete minimal/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /copy health/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /delete health/i })).toBeInTheDocument();
    expect(screen.getAllByText('Enabled').length).toBeGreaterThanOrEqual(1);
    expect(screen.queryByText('minimal')).not.toBeInTheDocument();
    expect(screen.queryByText('Minimal app')).not.toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /disable health/i })).toBeChecked();
    expect(screen.getByText('Minimal\\Health::handle')).toBeInTheDocument();
    expect(screen.getByText(/php class and method invoked/i)).toBeInTheDocument();
  });

  it('toggles endpoint enabled state from the endpoint table', async () => {
    renderDetail();

    fireEvent.click(await screen.findByRole('checkbox', { name: /disable health/i }));

    await waitFor(() => expect(api.updateEndpoint).toHaveBeenCalledWith('e1', { enabled: false }));
  });

  it('duplicates the current app', async () => {
    vi.mocked(api.copyApp).mockResolvedValueOnce({ ...app, id: 'a2', slug: 'minimal-copy', name: 'Minimal Copy' });
    renderDetail();

    fireEvent.click(await screen.findByRole('button', { name: /copy minimal/i }));

    await waitFor(() => expect(api.copyApp).toHaveBeenCalledWith('a1'));
  });

  it('deletes the current app after confirmation', async () => {
    vi.mocked(window.confirm).mockReturnValueOnce(true);
    vi.mocked(api.deleteApp).mockResolvedValueOnce(undefined);
    renderDetail();

    fireEvent.click(await screen.findByRole('button', { name: /delete minimal/i }));

    await waitFor(() => expect(api.deleteApp).toHaveBeenCalledWith('a1'));
  });

  it('copies endpoints from the app detail table', async () => {
    vi.mocked(api.copyEndpoint).mockResolvedValueOnce({ ...endpoint, id: 'e2', name: 'Health Copy', path: '/health-copy' });
    renderDetail();

    fireEvent.click(await screen.findByRole('button', { name: /copy health/i }));
    await waitFor(() => expect(api.copyEndpoint).toHaveBeenCalledWith('e1'));
  });

  it('deletes endpoints from the app detail table', async () => {
    vi.mocked(window.confirm).mockReturnValueOnce(true);
    vi.mocked(api.deleteEndpoint).mockResolvedValueOnce(undefined);
    renderDetail();

    fireEvent.click(await screen.findByRole('button', { name: /delete health/i }));
    await waitFor(() => expect(api.deleteEndpoint).toHaveBeenCalledWith('e1'));
  });
});
