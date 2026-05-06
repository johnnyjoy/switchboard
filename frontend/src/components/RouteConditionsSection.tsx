import { useState } from 'react';
import {
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  MenuItem,
  TextField,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import FilterListIcon from '@mui/icons-material/FilterList';
import { api, type DraftCondition, type EndpointCondition, type RouteConditionKind, type RouteConditionOp } from '../api';

const KINDS: { value: RouteConditionKind; label: string }[] = [
  { value: 'query', label: 'Query parameter' },
  { value: 'header', label: 'Header' },
  { value: 'cookie', label: 'Cookie' },
  { value: 'ip_allow', label: 'IP allowlist' },
  { value: 'ip_deny', label: 'IP denylist' },
  { value: 'user_agent', label: 'User-Agent' },
];

const OPS: { value: RouteConditionOp; label: string }[] = [
  { value: 'equals', label: 'Equals' },
  { value: 'contains', label: 'Contains' },
  { value: 'regex', label: 'Regex' },
  { value: 'present', label: 'Present (any value)' },
  { value: 'absent', label: 'Absent' },
  { value: 'in', label: 'In list' },
  { value: 'not_in', label: 'Not in list' },
];

type ConditionItem = EndpointCondition | (DraftCondition & { _draftId?: string });

interface RouteConditionsSectionProps {
  /** When set, conditions are loaded/saved via API; when omitted, use draft mode with onConditionsChange */
  endpointId?: string;
  conditions: ConditionItem[];
  onRefresh?: () => void;
  /** In draft mode (no endpointId), called when user adds/edits/deletes a condition */
  onConditionsChange?: (conditions: DraftCondition[]) => void;
}

const emptyForm = (): Partial<EndpointCondition> => ({
  kind: 'query',
  key: '',
  value: '',
  op: 'equals',
});

export default function RouteConditionsSection({ endpointId, conditions, onRefresh, onConditionsChange }: RouteConditionsSectionProps) {
  const isDraftMode = Boolean(onConditionsChange);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<ConditionItem | null>(null);
  const [editingIndex, setEditingIndex] = useState<number | null>(null);
  const [form, setForm] = useState<Partial<EndpointCondition>>(emptyForm());
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const needsKey = form.kind && ['query', 'header', 'cookie'].includes(form.kind);
  const isPresenceOp = form.op === 'present' || form.op === 'absent';
  const needsValue = !isPresenceOp;

  const openAdd = () => {
    setEditing(null);
    setEditingIndex(null);
    setForm(emptyForm());
    setError(null);
    setDialogOpen(true);
  };

  const openEdit = (c: ConditionItem, index: number) => {
    setEditing(c);
    setEditingIndex(index);
    setForm({
      kind: c.kind,
      key: c.key ?? '',
      value: c.value,
      op: c.op ?? 'equals',
    });
    setError(null);
    setDialogOpen(true);
  };

  const closeDialog = () => {
    setDialogOpen(false);
    setEditing(null);
    setEditingIndex(null);
    setForm(emptyForm());
    setError(null);
  };

  const handleSave = async () => {
    setError(null);
    setSaving(true);
    const payload: DraftCondition = {
      kind: form.kind!,
      key: needsKey && form.key ? String(form.key).trim() || null : null,
      value: isPresenceOp ? '' : String(form.value ?? '').trim(),
      op: needsKey ? (form.op ?? 'equals') : null,
    };
    try {
      if (isDraftMode && onConditionsChange) {
        const draftList = conditions.map((c) => ({
          kind: c.kind,
          key: c.key ?? null,
          value: c.value,
          op: c.op ?? null,
        })) as DraftCondition[];
        if (editingIndex !== null) {
          draftList[editingIndex] = payload;
        } else {
          draftList.push(payload);
        }
        onConditionsChange(draftList);
        closeDialog();
      } else if (endpointId && onRefresh) {
        if (editing && 'id' in editing) {
          await api.updateCondition(editing.id, payload);
        } else {
          await api.createCondition({ endpoint_id: endpointId, ...payload });
        }
        onRefresh();
        closeDialog();
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (item: ConditionItem, index: number) => {
    if (!window.confirm('Remove this condition?')) return;
    if (isDraftMode && onConditionsChange) {
      const draftList = conditions
        .map((c) => ({ kind: c.kind, key: c.key ?? null, value: c.value, op: c.op ?? null }))
        .filter((_, i) => i !== index) as DraftCondition[];
      onConditionsChange(draftList);
    } else if ('id' in item && endpointId && onRefresh) {
      try {
        await api.deleteCondition(item.id);
        onRefresh();
      } catch (err) {
        console.error(err);
      }
    }
  };

  const kindLabel = (kind: string) => KINDS.find((k) => k.value === kind)?.label ?? kind;
  const opLabel = (op: string | null) => (op ? OPS.find((o) => o.value === op)?.label ?? op : '');

  /** Human-readable one-line summary for a condition (e.g. "Query param api_key equals secret") */
  function conditionSummary(c: ConditionItem): string {
    const kind = kindLabel(c.kind);
    if (c.kind === 'ip_allow') return `IP allowlist: ${c.value || '—'}`;
    if (c.kind === 'ip_deny') return `IP denylist: ${c.value || '—'}`;
    if (c.kind === 'user_agent') return `User-Agent ${c.value ? `contains / matches: ${c.value}` : '—'}`;
    const key = c.key ? `"${c.key}"` : '';
    if (c.op === 'present') return `${kind} ${key} must be present`.trim() || kind;
    if (c.op === 'absent') return `${kind} ${key} must be absent`.trim() || kind;
    const op = opLabel(c.op);
    const val = c.value ? ` "${c.value}"` : '';
    return `${kind} ${key} ${op}${val}`.trim() || kind;
  }

  return (
    <Box>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
        <Typography variant="subtitle1" fontWeight={600}>Route conditions</Typography>
        <Button size="small" startIcon={<AddIcon />} variant="outlined" onClick={openAdd}>
          Add condition
        </Button>
      </Box>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
        Optional match rules: query params, headers, cookies, IP allow/deny, User-Agent. For query/header/cookie use equals, contains, regex, or require presence/absence. All conditions must match (AND). Path, method, and optional host are set in Match criteria above.
      </Typography>
      {conditions.length === 0 ? (
        <Box
          sx={{
            py: 2,
            px: 2,
            borderRadius: 0,
            bgcolor: 'action.hover',
            border: '1px dashed',
            borderColor: 'divider',
            textAlign: 'center',
          }}
        >
          <FilterListIcon sx={{ fontSize: 32, color: 'text.disabled', mb: 0.5 }} />
          <Typography variant="body2" color="text.secondary" display="block">
            No conditions. Requests match on host, path, and method only.
          </Typography>
          <Button size="small" startIcon={<AddIcon />} onClick={openAdd} sx={{ mt: 1 }}>
            Add first condition
          </Button>
        </Box>
      ) : (
        <Box component="ul" sx={{ m: 0, p: 0, listStyle: 'none' }}>
          {conditions.map((c, index) => (
            <Box
              component="li"
              key={'id' in c ? c.id : `draft-${index}`}
              sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 1,
                py: 1,
                px: 1.5,
                borderRadius: 0,
                bgcolor: 'action.hover',
                '&:not(:last-child)': { mb: 0.5 },
              }}
            >
              <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.8rem', wordBreak: 'break-word' }}>
                {conditionSummary(c)}
              </Typography>
              <Box sx={{ flexShrink: 0 }}>
                <IconButton size="small" aria-label="Edit" onClick={() => openEdit(c, index)}>
                  <EditIcon fontSize="small" />
                </IconButton>
                <IconButton size="small" aria-label="Delete" onClick={() => handleDelete(c, index)}>
                  <DeleteIcon fontSize="small" />
                </IconButton>
              </Box>
            </Box>
          ))}
        </Box>
      )}

      <Dialog open={dialogOpen} onClose={closeDialog} maxWidth="sm" fullWidth>
        <DialogTitle>{editing ? 'Edit condition' : 'Add condition'}</DialogTitle>
        <DialogContent>
          {error && (
            <Typography color="error" sx={{ mb: 1 }}>
              {error}
            </Typography>
          )}
          <TextField
            select
            fullWidth
            label="Type"
            value={form.kind ?? 'query'}
            onChange={(e) => setForm((f) => ({ ...f, kind: e.target.value as RouteConditionKind }))}
            margin="normal"
          >
            {KINDS.map((k) => (
              <MenuItem key={k.value} value={k.value}>
                {k.label}
              </MenuItem>
            ))}
          </TextField>
          {needsKey && (
            <TextField
              select
              fullWidth
              label="Operator"
              value={form.op ?? 'equals'}
              onChange={(e) => setForm((f) => ({ ...f, op: e.target.value as RouteConditionOp }))}
              margin="normal"
            >
              {OPS.map((o) => (
                <MenuItem key={o.value} value={o.value}>
                  {o.label}
                </MenuItem>
              ))}
            </TextField>
          )}
          {needsKey && (
            <TextField
              fullWidth
              label="Key / name"
              value={form.key ?? ''}
              onChange={(e) => setForm((f) => ({ ...f, key: e.target.value }))}
              margin="normal"
              placeholder={form.kind === 'query' ? 'e.g. api_key' : form.kind === 'header' ? 'e.g. x-request-id' : 'cookie name'}
            />
          )}
          {needsValue && (
            <TextField
              fullWidth
              label={form.kind === 'user_agent' ? 'Pattern or substring' : form.kind?.startsWith('ip_') ? 'IP or CIDR (comma-separated for multiple)' : 'Value or pattern'}
              value={form.value ?? ''}
              onChange={(e) => setForm((f) => ({ ...f, value: e.target.value }))}
              margin="normal"
              placeholder={form.kind === 'ip_allow' || form.kind === 'ip_deny' ? '192.168.1.0/24 or 10.0.0.1' : ''}
            />
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={closeDialog}>Cancel</Button>
          <Button variant="contained" onClick={handleSave} disabled={saving || (needsKey && !form.key?.trim()) || (needsValue && !form.value?.trim())}>
            {editing ? 'Update' : 'Add'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
