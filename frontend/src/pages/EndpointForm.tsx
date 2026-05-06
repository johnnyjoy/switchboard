import { useEffect, useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import {
  Box,
  Button,
  Card,
  CardContent,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  IconButton,
  MenuItem,
  Paper,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import { api, type App, type Endpoint, type EndpointPredicate, type PredicateOp, type PredicateSource, type PredicateValueType } from '../api';

const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
const SOURCES: PredicateSource[] = ['path', 'query', 'form', 'json', 'header', 'cookie'];
const OPS: PredicateOp[] = ['present', 'absent', 'equals', 'in', 'regex', 'type'];
const VALUE_TYPES: PredicateValueType[] = ['string', 'integer', 'number', 'boolean', 'date', 'datetime', 'uuid'];

type DraftPredicate = Omit<EndpointPredicate, 'id' | 'endpoint_id'>;

const blankPredicate: DraftPredicate = {
  source: 'query',
  name: '',
  op: 'present',
  value: null,
  value_type: null,
};

export default function EndpointForm() {
  const { appId, endpointId } = useParams<{ appId?: string; endpointId?: string }>();
  const isEdit = Boolean(endpointId);
  const [app, setApp] = useState<App | null>(null);
  const [endpoint, setEndpoint] = useState<Endpoint | null>(null);
  const [form, setForm] = useState({ name: 'New Endpoint', path: '/', methods: ['GET'], host: '', handler_class: '', handler_method: 'handle', enabled: true });
  const [predicates, setPredicates] = useState<EndpointPredicate[]>([]);
  const [draftPredicates, setDraftPredicates] = useState<DraftPredicate[]>([]);
  const [predicateDialogOpen, setPredicateDialogOpen] = useState(false);
  const [predicateEditing, setPredicateEditing] = useState<EndpointPredicate | DraftPredicate | null>(null);
  const [predicateEditIndex, setPredicateEditIndex] = useState<number | null>(null);
  const [predicateForm, setPredicateForm] = useState<DraftPredicate>(blankPredicate);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const navigate = useNavigate();

  const loadPredicates = () => {
    if (endpointId) api.getPredicates(endpointId).then(setPredicates).catch(() => setPredicates([]));
  };

  useEffect(() => {
    if (isEdit && endpointId) {
      Promise.all([api.getApps(), api.getEndpoints(), api.getPredicates(endpointId)])
        .then(([apps, endpoints, preds]) => {
          const ep = endpoints.find((e) => e.id === endpointId);
          const a = ep ? apps.find((x) => x.id === ep.app_id) : null;
          setApp(a ?? null);
          setEndpoint(ep ?? null);
          setPredicates(preds);
          if (ep) setForm({ name: ep.name, path: ep.path, methods: ep.methods, host: ep.host ?? '', handler_class: ep.handler_class, handler_method: ep.handler_method || 'handle', enabled: ep.enabled });
        })
        .catch((e) => setError(String(e.message)))
        .finally(() => setLoading(false));
    } else if (appId) {
      api.getApps()
        .then((apps) => { const a = apps.find((x) => x.id === appId); setApp(a ?? null); })
        .catch((e) => setError(String(e.message)))
        .finally(() => setLoading(false));
    } else setLoading(false);
  }, [appId, endpointId, isEdit]);

  const setMethod = (method: string, checked: boolean) => {
    setForm((current) => {
      const next = checked ? [...current.methods, method] : current.methods.filter((m) => m !== method);
      return { ...current, methods: Array.from(new Set(next)) };
    });
  };

  const savePredicate = async () => {
    if (!predicateForm.name.trim()) return;
    const next = { ...predicateForm, name: predicateForm.name.trim(), value: predicateForm.value || null, value_type: predicateForm.op === 'type' ? predicateForm.value_type : null };
    if (isEdit && predicateEditing && 'id' in predicateEditing) {
      await api.updatePredicate(predicateEditing.id, next);
      loadPredicates();
      setPredicateDialogOpen(false);
    } else if (!isEdit && predicateEditIndex !== null) {
      setDraftPredicates((prev) => { const copy = [...prev]; copy[predicateEditIndex] = next; return copy; });
      setPredicateDialogOpen(false);
    } else if (!isEdit) {
      setDraftPredicates((prev) => [...prev, next]);
      setPredicateDialogOpen(false);
    } else if (endpointId) {
      await api.createPredicate({ endpoint_id: endpointId, ...next });
      loadPredicates();
      setPredicateDialogOpen(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSaving(true);
    const path = form.path.startsWith('/') ? form.path : `/${form.path}`;
    try {
      if (form.methods.length === 0) throw new Error('Select at least one method');
      if (isEdit && endpointId) {
        await api.updateEndpoint(endpointId, { name: form.name, path, methods: form.methods, host: form.host || null, handler_class: form.handler_class, handler_method: form.handler_method || 'handle', enabled: form.enabled });
        navigate(`/endpoints/${endpointId}`);
      } else if (appId) {
        const created = await api.createEndpoint({ app_id: appId, name: form.name, path, methods: form.methods, host: form.host || null, handler_class: form.handler_class, handler_method: form.handler_method || 'handle', enabled: form.enabled });
        for (const predicate of draftPredicates) {
          await api.createPredicate({ endpoint_id: created.id, ...predicate });
        }
        navigate(`/endpoints/${created.id}`);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setSaving(false);
    }
  };

  const openPredicateDialog = (predicate?: EndpointPredicate | DraftPredicate, index?: number) => {
    setPredicateEditing(predicate ?? null);
    setPredicateEditIndex(index ?? null);
    setPredicateForm(predicate ? { source: predicate.source, name: predicate.name, op: predicate.op, value: predicate.value, value_type: predicate.value_type } : blankPredicate);
    setPredicateDialogOpen(true);
  };

  const renderPredicates = (rows: Array<EndpointPredicate | DraftPredicate>, isDraft: boolean) => {
    if (rows.length === 0) {
      return <Typography variant="body2" color="text.secondary">No predicates. Add one to require query, form, JSON, header, cookie, or path values.</Typography>;
    }
    return (
      <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 0 }}>
        <Table size="small">
          <TableHead><TableRow><TableCell>Source</TableCell><TableCell>Name</TableCell><TableCell>Operator</TableCell><TableCell>Value</TableCell><TableCell width={80} /></TableRow></TableHead>
          <TableBody>
            {rows.map((predicate, index) => (
              <TableRow key={'id' in predicate ? predicate.id : index}>
                <TableCell>{predicate.source}</TableCell>
                <TableCell>{predicate.name}</TableCell>
                <TableCell>{predicate.op}</TableCell>
                <TableCell>{predicate.op === 'type' ? predicate.value_type : Array.isArray(predicate.value) ? predicate.value.join(', ') : predicate.value ?? '-'}</TableCell>
                <TableCell>
                  <IconButton size="small" aria-label="Edit" onClick={() => openPredicateDialog(predicate, isDraft ? index : undefined)}><EditIcon fontSize="small" /></IconButton>
                  <IconButton size="small" aria-label="Delete" onClick={async () => {
                    if (isDraft) setDraftPredicates((prev) => prev.filter((_, i) => i !== index));
                    else if ('id' in predicate && window.confirm('Remove this predicate?')) { await api.deletePredicate(predicate.id); loadPredicates(); }
                  }}><DeleteIcon fontSize="small" /></IconButton>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    );
  };

  if (loading) return <Typography>Loading...</Typography>;
  if (appId && !app) return <Typography color="error">App not found</Typography>;
  if (isEdit && !endpoint) return <Typography color="error">Endpoint not found</Typography>;

  return (
    <Box component="form" onSubmit={handleSubmit} sx={{ maxWidth: 860 }}>
      <Box sx={{ mb: 3 }}>
        <Typography variant="overline" color="primary.light" fontWeight={800}>Endpoint builder</Typography>
        <Typography variant="h5" sx={{ mb: 0.75 }}>{isEdit ? 'Edit endpoint' : 'Create endpoint'}</Typography>
        <Typography color="text.secondary">
          {app ? `App: ${app.name}. ` : ''}Configure methods, typed path variables, predicates, and dispatch in one flow.
        </Typography>
      </Box>
      {error && <Typography color="error" sx={{ mb: 1 }}>{error}</Typography>}

      <Card variant="outlined" sx={{ mb: 2 }}>
        <CardContent>
          <Typography variant="overline" color="text.secondary" fontWeight={800}>01 Match</Typography>
          <Typography variant="subtitle1" fontWeight={600} sx={{ mb: 1.5 }}>Route contract</Typography>
          <TextField fullWidth label="Name *" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} required margin="normal" size="small" />
          <TextField fullWidth label="Path *" value={form.path} onChange={(e) => setForm((f) => ({ ...f, path: e.target.value }))} required margin="normal" size="small" placeholder="/articles/{id:integer}" helperText="Relative to app route prefix. Supports {name:type} path variables." />
          <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 1 }}>Methods</Typography>
          <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
            {METHODS.map((method) => (
              <FormControlLabel key={method} control={<Checkbox checked={form.methods.includes(method)} onChange={(e) => setMethod(method, e.target.checked)} />} label={method} />
            ))}
          </Box>
          <TextField fullWidth label="Host (optional)" value={form.host} onChange={(e) => setForm((f) => ({ ...f, host: e.target.value }))} margin="normal" size="small" placeholder="api.example.com" helperText="Leave blank to match any host" />
        </CardContent>
      </Card>

      <Card variant="outlined" sx={{ mb: 2 }}>
        <CardContent>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
            <Box>
              <Typography variant="overline" color="text.secondary" fontWeight={800}>02 Predicates</Typography>
              <Typography variant="subtitle1" fontWeight={600}>Request gates</Typography>
            </Box>
            <Button size="small" startIcon={<AddIcon />} variant="outlined" onClick={() => openPredicateDialog()}>
              Add predicate
            </Button>
          </Box>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
            Predicates are match-time gates over path, query, form, JSON, header, and cookie values.
          </Typography>
          {isEdit ? renderPredicates(predicates, false) : renderPredicates(draftPredicates, true)}
        </CardContent>
      </Card>

      <Card variant="outlined" sx={{ mb: 2 }}>
        <CardContent>
          <Typography variant="overline" color="text.secondary" fontWeight={800}>03 Dispatch</Typography>
          <Typography variant="subtitle1" fontWeight={600} sx={{ mb: 1.5 }}>PHP class and method</Typography>
          <TextField
            fullWidth
            label="PHP class *"
            value={form.handler_class}
            onChange={(e) => setForm((f) => ({ ...f, handler_class: e.target.value }))}
            required
            margin="normal"
            size="small"
            placeholder="News\\CreateArticle"
            helperText="App-owned PHP class to instantiate after match, predicates, and validation pass."
          />
          <TextField
            fullWidth
            label="Method *"
            value={form.handler_method}
            onChange={(e) => setForm((f) => ({ ...f, handler_method: e.target.value }))}
            required
            margin="normal"
            size="small"
            placeholder="handle"
            helperText="Method invoked on the class. Use handle by default, or __invoke for invokable classes."
          />
          <FormControlLabel control={<Switch checked={form.enabled} onChange={(e) => setForm((f) => ({ ...f, enabled: e.target.checked }))} />} label="Enabled" sx={{ display: 'block', mt: 1 }} />
        </CardContent>
      </Card>

      <Dialog open={predicateDialogOpen} onClose={() => setPredicateDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>{predicateEditing ? 'Edit predicate' : 'Add predicate'}</DialogTitle>
        <DialogContent>
          <TextField select fullWidth label="Source" value={predicateForm.source} onChange={(e) => setPredicateForm((f) => ({ ...f, source: e.target.value as PredicateSource }))} margin="normal" size="small">
            {SOURCES.map((source) => <MenuItem key={source} value={source}>{source}</MenuItem>)}
          </TextField>
          <TextField fullWidth label={predicateForm.source === 'json' ? 'Field path *' : 'Name *'} value={predicateForm.name} onChange={(e) => setPredicateForm((f) => ({ ...f, name: e.target.value }))} required margin="normal" size="small" placeholder={predicateForm.source === 'json' ? 'user.id' : 'token'} />
          <TextField select fullWidth label="Operator" value={predicateForm.op} onChange={(e) => setPredicateForm((f) => ({ ...f, op: e.target.value as PredicateOp }))} margin="normal" size="small">
            {OPS.map((op) => <MenuItem key={op} value={op}>{op}</MenuItem>)}
          </TextField>
          {predicateForm.op === 'type' ? (
            <TextField select fullWidth label="Value type" value={predicateForm.value_type ?? ''} onChange={(e) => setPredicateForm((f) => ({ ...f, value_type: e.target.value as PredicateValueType }))} margin="normal" size="small">
              {VALUE_TYPES.map((type) => <MenuItem key={type} value={type}>{type}</MenuItem>)}
            </TextField>
          ) : !['present', 'absent'].includes(predicateForm.op) ? (
            <TextField fullWidth label="Value" value={Array.isArray(predicateForm.value) ? predicateForm.value.join(', ') : predicateForm.value ?? ''} onChange={(e) => setPredicateForm((f) => ({ ...f, value: e.target.value }))} margin="normal" size="small" helperText="Use comma-separated values for in." />
          ) : null}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPredicateDialogOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={savePredicate} disabled={!predicateForm.name.trim() || (predicateForm.op === 'type' && !predicateForm.value_type)}>
            {predicateEditing ? 'Update' : 'Add'}
          </Button>
        </DialogActions>
      </Dialog>

      <Box sx={{ mt: 2, display: 'flex', gap: 1 }}>
        <Button type="submit" variant="contained" disabled={saving}>{isEdit ? 'Save' : 'Create'}</Button>
        <Button component={Link} to={isEdit ? `/endpoints/${endpointId}` : `/apps/${appId}`}>Cancel</Button>
      </Box>
    </Box>
  );
}
