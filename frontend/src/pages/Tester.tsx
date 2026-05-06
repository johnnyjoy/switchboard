import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Alert, Box, Button, Card, CardContent, MenuItem, Stack, TextField, Typography } from '@mui/material';
import { api, type TestResult } from '../api';
import { Tag } from '../components/ui';

const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

function parsePairs(input: string): Record<string, string> {
  return Object.fromEntries(
    input
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean)
      .map((line) => {
        const [key, ...rest] = line.split('=');
        return [key.trim(), rest.join('=').trim()];
      })
      .filter(([key]) => key)
  );
}

export default function Tester() {
  const [host, setHost] = useState('');
  const [path, setPath] = useState('/health');
  const [method, setMethod] = useState('GET');
  const [query, setQuery] = useState('');
  const [headers, setHeaders] = useState('');
  const [cookies, setCookies] = useState('');
  const [form, setForm] = useState('');
  const [contentType, setContentType] = useState('application/json');
  const [body, setBody] = useState('');
  const [result, setResult] = useState<TestResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [searchParams] = useSearchParams();

  useEffect(() => {
    const endpointId = searchParams.get('endpoint');
    if (!endpointId) return;
    api.getEndpoints().then((endpoints) => {
      const endpoint = endpoints.find((ep) => ep.id === endpointId);
      if (!endpoint) return;
      setHost(endpoint.host ?? '');
      setPath(endpoint.path);
      setMethod(endpoint.methods[0] ?? 'GET');
    }).catch(() => undefined);
  }, [searchParams]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setResult(null);
    setLoading(true);
    const pathNorm = path.startsWith('/') ? path : `/${path}`;
    let bodyPayload: unknown = null;
    if (body.trim()) {
      try {
        bodyPayload = JSON.parse(body.trim());
      } catch {
        bodyPayload = body.trim();
      }
    }
    try {
      const res = await api.testRequest({
        host: host.trim() || undefined,
        path: pathNorm,
        method,
        query: parsePairs(query),
        headers: parsePairs(headers),
        cookies: parsePairs(cookies),
        form: parsePairs(form),
        body: bodyPayload,
        content_type: contentType,
      });
      setResult(res);
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setLoading(false);
    }
  };

  const traceChip = (status: string | undefined) => (
    <Tag tone={status === 'passed' ? 'success' : status === 'failed' ? 'danger' : 'default'}>
      {status ?? 'not run'}
    </Tag>
  );

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="overline" color="primary.light" fontWeight={800}>Verification</Typography>
        <Typography variant="h5" sx={{ mb: 0.75 }}>Endpoint tester</Typography>
        <Typography color="text.secondary">
          Run a representative request and inspect the current registry's match, validation, and dispatch trace.
        </Typography>
      </Box>
      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'minmax(320px, 0.9fr) minmax(0, 1.1fr)' }, gap: 2, alignItems: 'start' }}>
        <Card variant="outlined">
          <CardContent>
            <Typography variant="subtitle1" fontWeight={600} sx={{ mb: 0.5 }}>Request composer</Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
              Paths are relative to the app route, for example <code>/health</code>.
            </Typography>
            <Box component="form" onSubmit={handleSubmit}>
              <TextField fullWidth label="Host (optional)" value={host} onChange={(e) => setHost(e.target.value)} margin="normal" placeholder="localhost" />
              <TextField fullWidth label="Path *" value={path} onChange={(e) => setPath(e.target.value)} required margin="normal" placeholder="/health" />
              <TextField select fullWidth label="Method" value={method} onChange={(e) => setMethod(e.target.value)} margin="normal">
                {METHODS.map((m) => <MenuItem key={m} value={m}>{m}</MenuItem>)}
              </TextField>
              <TextField fullWidth label="Query params" value={query} onChange={(e) => setQuery(e.target.value)} margin="normal" multiline rows={3} placeholder={'limit=10\nmode=fast'} />
              <TextField fullWidth label="Headers" value={headers} onChange={(e) => setHeaders(e.target.value)} margin="normal" multiline rows={3} placeholder={'x-api-key=secret'} />
              <TextField fullWidth label="Cookies" value={cookies} onChange={(e) => setCookies(e.target.value)} margin="normal" multiline rows={3} placeholder={'session=abc'} />
              <TextField fullWidth label="Form fields" value={form} onChange={(e) => setForm(e.target.value)} margin="normal" multiline rows={3} placeholder={'title=Hello'} />
              <TextField select fullWidth label="Content-Type" value={contentType} onChange={(e) => setContentType(e.target.value)} margin="normal">
                <MenuItem value="application/json">application/json</MenuItem>
                <MenuItem value="application/x-www-form-urlencoded">application/x-www-form-urlencoded</MenuItem>
                <MenuItem value="text/plain">text/plain</MenuItem>
              </TextField>
              <TextField fullWidth label="Body" value={body} onChange={(e) => setBody(e.target.value)} margin="normal" multiline rows={5} placeholder="{}" />
              <Button type="submit" variant="contained" disabled={loading} sx={{ mt: 1 }}>{loading ? 'Running…' : 'Run test'}</Button>
            </Box>
          </CardContent>
        </Card>

        <Card variant="outlined">
          <CardContent>
            <Typography variant="subtitle1" fontWeight={600} sx={{ mb: 0.5 }}>Trace</Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
              Follow the request through Match, Validate, and Dispatch.
            </Typography>
            {error && <Alert severity="error" sx={{ mb: 1.5 }}>{error}</Alert>}
            {!result && !error && (
              <Box sx={{ py: 3, px: 2, border: '1px dashed', borderColor: 'divider', borderRadius: 0, textAlign: 'center' }}>
                <Typography variant="body2" color="text.secondary">Run a test request to see the route trace.</Typography>
              </Box>
            )}
            {result && (
              <Box>
                <Typography variant="subtitle2" fontWeight={600} color={result.matched ? 'success.main' : 'text.secondary'}>
                  {result.matched ? 'Matched' : 'No match'}
                </Typography>
                {result.endpoint && (
                  <Typography sx={{ mt: 1, overflowWrap: 'anywhere' }}>
                    Endpoint: {result.endpoint.name} · {result.endpoint.methods.join(', ')} {result.endpoint.path} · Dispatch target: <code>{result.endpoint.handler_class}::{result.endpoint.handler_method}</code>
                  </Typography>
                )}
                {result.app && (
                  <Typography color="text.secondary" sx={{ overflowWrap: 'anywhere' }}>App: {result.app.name} (slug: {result.app.slug})</Typography>
                )}
                {!result.matched && <Typography color="text.secondary" sx={{ mt: 1 }}>No endpoint in the registry matches this host/path/method.</Typography>}
                {result.trace && (
                  <Stack spacing={1} sx={{ mt: 2 }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2 }}>
                      <Typography>1. Match</Typography>
                      {traceChip(result.trace.match.status)}
                    </Box>
                    {result.trace.match.pathParams && Object.keys(result.trace.match.pathParams).length > 0 && (
                      <Typography color="text.secondary" variant="body2">
                        Path params: {JSON.stringify(result.trace.match.pathParams)}
                      </Typography>
                    )}
                    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2 }}>
                      <Typography>2. Validate</Typography>
                      {traceChip(result.trace.validation.status)}
                    </Box>
                    {result.trace.validation.errors.length > 0 && (
                      <Typography color="error" variant="body2">
                        {result.trace.validation.errors.join('; ')}
                      </Typography>
                    )}
                    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2 }}>
                      <Typography>3. Dispatch</Typography>
                      {traceChip(result.trace.dispatch?.status)}
                    </Box>
                    {result.trace.dispatch?.response_status && (
                      <Typography color="text.secondary" variant="body2">
                        Response status: {result.trace.dispatch.response_status}
                      </Typography>
                    )}
                  </Stack>
                )}
              </Box>
            )}
          </CardContent>
        </Card>
      </Box>
    </Box>
  );
}
