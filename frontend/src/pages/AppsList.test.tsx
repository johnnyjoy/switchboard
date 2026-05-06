import { describe, it, expect, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import AppsList from './AppsList';
import { api } from '../api';

vi.mock('../api', () => ({
  api: {
    getApps: vi.fn(),
    getEndpoints: vi.fn(),
    copyApp: vi.fn(),
    deleteApp: vi.fn(),
  },
}));

const apps = [
  { id: 'a1', slug: 'minimal', name: 'Minimal', description: null, app_path: null, enabled: true, created_at: '', updated_at: '' },
];

function renderList() {
  return render(
    <MemoryRouter future={{ v7_relativeSplatPath: true, v7_startTransition: true }}>
      <AppsList />
    </MemoryRouter>
  );
}

describe('AppsList', () => {
  beforeEach(() => {
    vi.mocked(api.getApps).mockReset();
    vi.mocked(api.getEndpoints).mockReset();
    vi.mocked(api.copyApp).mockReset();
    vi.mocked(api.deleteApp).mockReset();
    vi.mocked(api.getApps).mockResolvedValue(apps);
    vi.mocked(api.getEndpoints).mockResolvedValue([
      { id: 'e1', app_id: 'a1', name: 'Health', host: null, path: '/health', methods: ['GET'], handler_class: 'Minimal\\Health', handler_method: 'handle', enabled: true, created_at: '', updated_at: '' },
    ]);
    vi.spyOn(window, 'confirm').mockReset();
  });

  it('shows icon actions for editing, duplicating, and deleting apps', async () => {
    renderList();

    expect(await screen.findByText('Minimal')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /edit/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /copy/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /delete/i })).toBeInTheDocument();
    expect(screen.getAllByText('Enabled').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('1 routes')).toBeInTheDocument();
  });

  it('duplicates an app through the API', async () => {
    vi.mocked(api.copyApp).mockResolvedValueOnce({ ...apps[0], id: 'a2', slug: 'minimal-copy', name: 'Minimal Copy' });
    renderList();

    fireEvent.click(await screen.findByRole('button', { name: /copy/i }));

    await waitFor(() => expect(api.copyApp).toHaveBeenCalledWith('a1'));
  });

  it('deletes an app after confirmation and refreshes the list', async () => {
    vi.mocked(window.confirm).mockReturnValueOnce(true);
    vi.mocked(api.deleteApp).mockResolvedValueOnce(undefined);
    renderList();

    fireEvent.click(await screen.findByRole('button', { name: /delete/i }));

    await waitFor(() => expect(api.deleteApp).toHaveBeenCalledWith('a1'));
    expect(api.getApps).toHaveBeenCalledTimes(2);
    expect(api.getEndpoints).toHaveBeenCalledTimes(2);
  });
});
