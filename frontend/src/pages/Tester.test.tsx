import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Tester from './Tester';
import { api } from '../api';

vi.mock('../api', () => ({
  api: {
    getEndpoints: vi.fn(),
    testRequest: vi.fn(),
  },
}));

function renderTester(path = '/tester') {
  return render(
    <MemoryRouter initialEntries={[path]} future={{ v7_relativeSplatPath: true, v7_startTransition: true }}>
      <Tester />
    </MemoryRouter>
  );
}

describe('Tester', () => {
  beforeEach(() => {
    vi.mocked(api.testRequest).mockReset();
    vi.mocked(api.getEndpoints).mockReset();
    vi.mocked(api.getEndpoints).mockResolvedValue([]);
  });

  it('renders endpoint tester form with path and method', () => {
    renderTester();
    expect(screen.getByText('Endpoint tester')).toBeInTheDocument();
    expect(screen.getByText('Request composer')).toBeInTheDocument();
    expect(screen.getByText('Trace')).toBeInTheDocument();
    expect(screen.getByLabelText(/path/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/method/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /run test/i })).toBeInTheDocument();
    expect(screen.getByText('Run a test request to see the route trace.')).toBeInTheDocument();
  });

  it('default path is /health', () => {
    renderTester();
    const pathInput = screen.getByLabelText(/path/i);
    expect((pathInput as HTMLInputElement).value).toBe('/health');
  });

  it('calls testRequest on submit and shows matched result', async () => {
    vi.mocked(api.testRequest).mockResolvedValueOnce({
      matched: true,
      endpoint: { id: 'e1', name: 'Health', path: '/health', methods: ['GET'], handler_class: 'Minimal\\Health', handler_method: 'handle' },
      app: { id: 'a1', name: 'Minimal', slug: 'minimal' },
      trace: {
        match: { status: 'passed', pathParams: { id: '42' } },
        validation: { status: 'passed', errors: [] },
        dispatch: { status: 'passed', response_status: 200 },
      },
      runtimeAvailable: true,
      runtimeResponse: {},
    });
    renderTester();
    fireEvent.click(screen.getByRole('button', { name: /run test/i }));
    await waitFor(() => expect(api.testRequest).toHaveBeenCalledWith(expect.objectContaining({ path: '/health', method: 'GET' })));
    expect(screen.getByText('Matched')).toBeInTheDocument();
    expect(screen.getByText(/Minimal\\Health::handle/)).toBeInTheDocument();
    expect(screen.getByText('1. Match')).toBeInTheDocument();
    expect(screen.getByText('Path params: {"id":"42"}')).toBeInTheDocument();
    expect(screen.getByText('2. Validate')).toBeInTheDocument();
    expect(screen.getByText('3. Dispatch')).toBeInTheDocument();
    expect(screen.getByText('Response status: 200')).toBeInTheDocument();
  });

  it('shows error when testRequest fails', async () => {
    vi.mocked(api.testRequest).mockRejectedValueOnce(new Error('Network error'));
    renderTester();
    fireEvent.click(screen.getByRole('button', { name: /run test/i }));
    await waitFor(() => expect(screen.getByText('Network error')).toBeInTheDocument());
  });
});
