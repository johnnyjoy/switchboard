const API = '/api';

const BACKEND_UNREACHABLE_MSG =
  'Backend unreachable. Start the PHP backend in another terminal: php -S localhost:8080 -t . scripts/php-router.php';

async function request<T>(path: string, options?: RequestInit): Promise<T> {
  let res: Response;
  try {
    res = await fetch(`${API}${path}`, {
      ...options,
      headers: { 'Content-Type': 'application/json', ...options?.headers },
    });
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    if (/fetch|network|connection|refused|econnrefused/i.test(msg))
      throw new Error(BACKEND_UNREACHABLE_MSG);
    throw e;
  }
  if (res.status === 502 || res.status === 503) throw new Error(BACKEND_UNREACHABLE_MSG);
  if (res.status === 204) return undefined as T;
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { error?: string })?.error || res.statusText);
  return data as T;
}

export interface App {
  id: string;
  slug: string;
  name: string;
  description: string | null;
  app_path: string | null;
  enabled: boolean;
  created_at: string;
  updated_at: string;
}

export interface Endpoint {
  id: string;
  app_id: string;
  name: string;
  host: string | null;
  path: string;
  methods: string[];
  handler_class: string;
  handler_method: string;
  enabled: boolean;
  created_at: string;
  updated_at: string;
}

export type PredicateSource = 'path' | 'query' | 'form' | 'json' | 'header' | 'cookie';
export type PredicateOp = 'present' | 'absent' | 'equals' | 'in' | 'regex' | 'type';
export type PredicateValueType = 'string' | 'integer' | 'number' | 'boolean' | 'date' | 'datetime' | 'uuid';

export interface EndpointPredicate {
  id: string;
  endpoint_id: string;
  source: PredicateSource;
  name: string;
  op: PredicateOp;
  value: string | string[] | null;
  value_type: PredicateValueType | null;
}

export interface EndpointValidation {
  id: string;
  endpoint_id: string;
  content_type: string;
  schema: unknown;
}

export type RouteConditionKind = 'query' | 'header' | 'cookie' | 'ip_allow' | 'ip_deny' | 'user_agent';
export type RouteConditionOp = 'equals' | 'contains' | 'regex' | 'present' | 'absent' | 'in' | 'not_in';

/** Draft condition (no id/endpoint_id) for create flow */
export interface DraftCondition {
  kind: RouteConditionKind;
  key: string | null;
  value: string;
  op: RouteConditionOp | null;
}

export interface EndpointCondition {
  id: string;
  endpoint_id: string;
  kind: RouteConditionKind;
  key: string | null;
  value: string;
  op: RouteConditionOp | null;
  created_at?: string;
  updated_at?: string;
}

export interface TestResult {
  matched: boolean;
  endpoint: { id: string; name: string; path: string; methods: string[]; handler_class: string; handler_method: string } | null;
  app: { id: string; name: string; slug: string } | null;
  trace?: {
    match: { status: 'passed' | 'failed'; pathPrefix?: unknown; pathParams?: Record<string, string> };
    validation: { status: 'passed' | 'failed'; errors: string[] };
    dispatch: { status: 'passed' | 'failed' | 'skipped'; response_status?: number | null; error?: string; reason?: string } | null;
  };
  runtimeAvailable: boolean;
  runtimeResponse: unknown;
}

export const api = {
  getApps: () => request<App[]>('/apps'),
  createApp: (body: Partial<App>) => request<App>('/apps', { method: 'POST', body: JSON.stringify(body) }),
  updateApp: (id: string, body: Partial<App>) => request<App>(`/apps/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
  copyApp: (id: string) => request<App>(`/apps/${id}/copy`, { method: 'POST' }),
  deleteApp: (id: string) => request<void>(`/apps/${id}`, { method: 'DELETE' }),

  getEndpoints: (appId?: string) => request<Endpoint[]>(appId ? `/endpoints?app_id=${encodeURIComponent(appId)}` : '/endpoints'),
  createEndpoint: (body: Partial<Endpoint>) => request<Endpoint>('/endpoints', { method: 'POST', body: JSON.stringify(body) }),
  updateEndpoint: (id: string, body: Partial<Endpoint>) => request<Endpoint>(`/endpoints/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
  copyEndpoint: (id: string) => request<Endpoint>(`/endpoints/${id}/copy`, { method: 'POST' }),
  deleteEndpoint: (id: string) => request<void>(`/endpoints/${id}`, { method: 'DELETE' }),

  getPredicates: (endpointId: string) => request<EndpointPredicate[]>(`/endpoint-predicates?endpoint_id=${encodeURIComponent(endpointId)}`),
  createPredicate: (body: Partial<EndpointPredicate>) => request<EndpointPredicate>('/endpoint-predicates', { method: 'POST', body: JSON.stringify(body) }),
  updatePredicate: (id: string, body: Partial<EndpointPredicate>) => request<EndpointPredicate>(`/endpoint-predicates/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
  deletePredicate: (id: string) => request<void>(`/endpoint-predicates/${id}`, { method: 'DELETE' }),

  getValidations: (endpointId: string) => request<EndpointValidation[]>(`/endpoint-validations?endpoint_id=${encodeURIComponent(endpointId)}`),
  createValidation: (body: Partial<EndpointValidation>) => request<EndpointValidation>('/endpoint-validations', { method: 'POST', body: JSON.stringify(body) }),
  updateValidation: (id: string, body: Partial<EndpointValidation>) => request<EndpointValidation>(`/endpoint-validations/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
  deleteValidation: (id: string) => request<void>(`/endpoint-validations/${id}`, { method: 'DELETE' }),

  getConditions: (endpointId: string) => request<EndpointCondition[]>(`/endpoint-conditions?endpoint_id=${encodeURIComponent(endpointId)}`),
  createCondition: (body: Partial<EndpointCondition>) => request<EndpointCondition>('/endpoint-conditions', { method: 'POST', body: JSON.stringify(body) }),
  updateCondition: (id: string, body: Partial<EndpointCondition>) => request<EndpointCondition>(`/endpoint-conditions/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
  deleteCondition: (id: string) => request<void>(`/endpoint-conditions/${id}`, { method: 'DELETE' }),

  testRequest: (body: { host?: string; path: string; method: string; query?: Record<string, string>; headers?: Record<string, string>; cookies?: Record<string, string>; form?: Record<string, string>; json?: unknown; body?: unknown; content_type?: string }) =>
    request<TestResult>('/test-request', { method: 'POST', body: JSON.stringify(body) }),
};
