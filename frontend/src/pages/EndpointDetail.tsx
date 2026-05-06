import { type ReactNode, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  Alert,
  Box,
  Card,
  CardContent,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import AccountTreeIcon from '@mui/icons-material/AccountTree';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import FactCheckIcon from '@mui/icons-material/FactCheck';
import PlayArrowIcon from '@mui/icons-material/PlayArrow';
import RouteIcon from '@mui/icons-material/Route';
import ScienceIcon from '@mui/icons-material/Science';
import { api, type App, type Endpoint, type EndpointPredicate, type EndpointValidation } from '../api';
import { ActionIcon, Tag } from '../components/ui';

function StageTitle({ step, title, icon }: { step: string; title: string; icon: ReactNode }) {
  return (
    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25, mb: 1.5 }}>
      <Box
        sx={{
          width: 34,
          height: 34,
          display: 'grid',
          placeItems: 'center',
          borderRadius: 0,
          bgcolor: 'rgba(88, 166, 255, 0.12)',
          color: 'primary.main',
          border: '1px solid rgba(88, 166, 255, 0.25)',
        }}
      >
        {icon}
      </Box>
      <Box>
        <Typography variant="caption" color="text.secondary" fontWeight={800}>{step}</Typography>
        <Typography variant="subtitle1" fontWeight={750}>{title}</Typography>
      </Box>
    </Box>
  );
}

export default function EndpointDetail() {
  const { endpointId } = useParams<{ endpointId: string }>();
  const navigate = useNavigate();
  const [app, setApp] = useState<App | null>(null);
  const [endpoint, setEndpoint] = useState<Endpoint | null>(null);
  const [predicates, setPredicates] = useState<EndpointPredicate[]>([]);
  const [validations, setValidations] = useState<EndpointValidation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const loadData = () => {
    if (!endpointId) return;
    Promise.all([
      api.getApps(),
      api.getEndpoints(),
      api.getPredicates(endpointId),
      api.getValidations(endpointId),
    ])
      .then(([apps, endpoints, preds, vals]) => {
        const ep = endpoints.find((e) => e.id === endpointId);
        const a = ep ? apps.find((x) => x.id === ep.app_id) : null;
        setApp(a ?? null);
        setEndpoint(ep ?? null);
        setPredicates(preds);
        setValidations(vals);
      })
      .catch((e) => setError(String(e.message)))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, [endpointId]);

  const handleCopy = async () => {
    if (!endpoint) return;
    setActionError(null);
    try {
      const copied = await api.copyEndpoint(endpoint.id);
      navigate(`/endpoints/${copied.id}`);
    } catch (err) {
      setActionError(err instanceof Error ? err.message : String(err));
    }
  };

  const handleDelete = async () => {
    if (!endpoint) return;
    if (!window.confirm(`Delete "${endpoint.name}"? This cannot be undone.`)) {
      return;
    }
    setActionError(null);
    try {
      await api.deleteEndpoint(endpoint.id);
      navigate(app ? `/apps/${app.id}` : '/');
    } catch (err) {
      setActionError(err instanceof Error ? err.message : String(err));
    }
  };

  if (error) return <Typography color="error">{error}</Typography>;
  if (loading || !endpoint) return <Typography>Loading…</Typography>;

  return (
    <Box>
      <Card variant="outlined" sx={{ mb: 3 }}>
        <CardContent sx={{ p: { xs: 2.5, md: 3 } }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: { xs: 'flex-start', md: 'center' }, gap: 2, flexDirection: { xs: 'column', md: 'row' } }}>
            <Box>
              <Typography variant="overline" color="primary.light" fontWeight={800}>Endpoint</Typography>
              <Typography variant="h5" fontWeight={750}>{endpoint.name}</Typography>
              <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap', mt: 1 }}>
                <Tag tone={endpoint.enabled ? 'success' : 'default'}>{endpoint.enabled ? 'Enabled' : 'Disabled'}</Tag>
                <Tag tone="primary">{endpoint.methods.join(', ')}</Tag>
                <Tag sx={{ fontFamily: 'monospace' }}>{endpoint.path}</Tag>
              </Box>
              <Typography color="text.secondary" variant="body2" sx={{ mt: 1.5, overflowWrap: 'anywhere' }}>
                App: {app ? <Link to={`/apps/${app.id}`}>{app.name}</Link> : '—'}
              </Typography>
            </Box>
            <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
              <ActionIcon label={`Edit ${endpoint.name}`} component={Link} to={`/endpoints/${endpoint.id}/edit`}>
                <EditIcon fontSize="small" />
              </ActionIcon>
              <ActionIcon label={`Copy ${endpoint.name}`} onClick={handleCopy}>
                <ContentCopyIcon fontSize="small" />
              </ActionIcon>
              <ActionIcon label={`Delete ${endpoint.name}`} color="error" onClick={handleDelete}>
                <DeleteIcon fontSize="small" />
              </ActionIcon>
              <ActionIcon label={`Test ${endpoint.name}`} component={Link} to={`/tester?endpoint=${endpoint.id}`}>
                <ScienceIcon fontSize="small" />
              </ActionIcon>
            </Box>
          </Box>
        </CardContent>
      </Card>
      {actionError && <Alert severity="error" sx={{ mb: 2 }}>{actionError}</Alert>}

      <Card variant="outlined" sx={{ mb: 2 }}>
        <CardContent>
          <StageTitle step="01" title="Match" icon={<RouteIcon fontSize="small" />} />
          <Box component="dl" sx={{ m: 0, display: 'grid', gridTemplateColumns: 'auto 1fr', gap: '8px 16px' }}>
            <Typography component="dt" color="text.secondary" variant="body2">Host</Typography>
            <Typography component="dd" variant="body2">{endpoint.host || 'Any'}</Typography>
            <Typography component="dt" color="text.secondary" variant="body2">Path</Typography>
            <Typography component="dd" variant="body2"><Tag sx={{ fontFamily: 'monospace' }}>{endpoint.path}</Tag></Typography>
            <Typography component="dt" color="text.secondary" variant="body2">Methods</Typography>
            <Typography component="dd" variant="body2"><Tag tone="primary">{endpoint.methods.join(', ')}</Tag></Typography>
          </Box>
        </CardContent>
      </Card>

      <Card variant="outlined" sx={{ mb: 2 }}>
        <CardContent>
          <StageTitle step="02" title="Predicates" icon={<AccountTreeIcon fontSize="small" />} />
          {predicates.length === 0 ? (
            <Box sx={{ border: '1px dashed', borderColor: 'divider', borderRadius: 0, p: 2 }}>
              <Typography color="text.secondary" variant="body2">No endpoint predicates are configured.</Typography>
            </Box>
          ) : (
            <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 0 }}>
              <Table size="small">
                <TableHead><TableRow><TableCell>Source</TableCell><TableCell>Name</TableCell><TableCell>Operator</TableCell><TableCell>Value</TableCell></TableRow></TableHead>
                <TableBody>
                  {predicates.map((p) => (
                    <TableRow key={p.id}>
                      <TableCell>{p.source}</TableCell>
                      <TableCell>{p.name}</TableCell>
                      <TableCell>{p.op}</TableCell>
                      <TableCell>{p.op === 'type' ? p.value_type : Array.isArray(p.value) ? p.value.join(', ') : p.value ?? '-'}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </CardContent>
      </Card>

      <Card variant="outlined" sx={{ mb: 2 }}>
        <CardContent>
          <StageTitle step="03" title="Body Validation" icon={<FactCheckIcon fontSize="small" />} />
          {validations.length === 0 ? (
            <Box sx={{ border: '1px dashed', borderColor: 'divider', borderRadius: 0, p: 2 }}>
              <Typography color="text.secondary" variant="body2">No body schema validations are configured.</Typography>
            </Box>
          ) : (
            <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 0 }}>
              <Table size="small">
                <TableHead><TableRow><TableCell>Content-Type</TableCell><TableCell>Schema</TableCell></TableRow></TableHead>
                <TableBody>
                  {validations.map((v) => (
                    <TableRow key={v.id}><TableCell>{v.content_type}</TableCell><TableCell><code>{typeof v.schema === 'string' ? v.schema : JSON.stringify(v.schema)}</code></TableCell></TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </CardContent>
      </Card>

      <Card variant="outlined">
        <CardContent>
          <StageTitle step="04" title="Dispatch Target" icon={<PlayArrowIcon fontSize="small" />} />
          <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
            <code>{endpoint.handler_class}::{endpoint.handler_method}</code> is invoked after match and validation pass.
          </Typography>
          <Box sx={{ border: '1px dashed', borderColor: 'divider', borderRadius: 0, p: 2, display: 'flex', gap: 1, alignItems: 'center' }}>
            <CheckCircleIcon color="success" fontSize="small" />
            <Typography color="text.secondary" variant="body2">Dispatch occurs only after method, path, host, predicates, and body validation pass.</Typography>
          </Box>
        </CardContent>
      </Card>
    </Box>
  );
}
