import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import App from './App';
import { api } from './api';

vi.mock('./api', () => ({
  api: {
    getApps: vi.fn(),
    getEndpoints: vi.fn(),
  },
}));

function renderWithRouter(initialEntries = ['/']) {
  return render(
    <MemoryRouter initialEntries={initialEntries} future={{ v7_relativeSplatPath: true, v7_startTransition: true }}>
      <App />
    </MemoryRouter>
  );
}

describe('App', () => {
  beforeEach(() => {
    vi.mocked(api.getApps).mockReset();
    vi.mocked(api.getEndpoints).mockReset();
    vi.mocked(api.getApps).mockResolvedValue([]);
    vi.mocked(api.getEndpoints).mockResolvedValue([]);
  });

  it('renders Switchboard brand and nav', async () => {
    renderWithRouter();
    expect(screen.getByText('Switchboard')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Apps' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Endpoint tester' })).toBeInTheDocument();
    expect(await screen.findByText(/no apps yet/i)).toBeInTheDocument();
  });

  it('shows Apps list at /', async () => {
    renderWithRouter(['/']);
    expect(await screen.findByRole('heading', { level: 5, name: 'Applications' })).toBeInTheDocument();
  });

  it('shows Endpoint tester at /tester', () => {
    renderWithRouter(['/tester']);
    expect(screen.getByRole('heading', { level: 5, name: 'Endpoint tester' })).toBeInTheDocument();
  });
});
