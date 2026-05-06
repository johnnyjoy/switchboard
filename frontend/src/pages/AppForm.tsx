import { useEffect, useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { Alert, Box, Button, Card, CardContent, FormControlLabel, Switch, TextField, Typography } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import SaveIcon from '@mui/icons-material/Save';
import { api, type App } from '../api';

export default function AppForm() {
  const { appId } = useParams<{ appId: string }>();
  const isEdit = Boolean(appId);
  const [app, setApp] = useState<App | null>(null);
  const [form, setForm] = useState({ name: '', slug: '', description: '', app_path: '', enabled: true });
  const [loading, setLoading] = useState(isEdit);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    if (!isEdit) {
      setForm((f) => ({ ...f, name: '', slug: '', description: '', app_path: '', enabled: true }));
      setLoading(false);
      return;
    }
    if (!appId) return;
    api.getApps()
      .then((apps) => {
        const a = apps.find((x) => x.id === appId);
        if (a) {
          setApp(a);
          setForm({ name: a.name, slug: a.slug, description: a.description ?? '', app_path: a.app_path ?? '', enabled: a.enabled });
        }
      })
      .catch((e) => setError(String(e.message)))
      .finally(() => setLoading(false));
  }, [appId, isEdit]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      if (isEdit && appId) {
        await api.updateApp(appId, { name: form.name, description: form.description || null, app_path: form.app_path || null, enabled: form.enabled });
        navigate(`/apps/${appId}`);
      } else {
        const created = await api.createApp({ name: form.name, slug: form.slug, description: form.description || null, app_path: form.app_path || null, enabled: form.enabled });
        navigate(`/apps/${created.id}`);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <Typography>Loading…</Typography>;
  if (isEdit && appId && !app) return <Typography color="error">App not found</Typography>;

  return (
    <Box component="form" onSubmit={handleSubmit} sx={{ maxWidth: 760 }}>
      <Typography variant="overline" color="primary.light" fontWeight={800}>Application</Typography>
      <Typography variant="h5" sx={{ mb: 1 }}>{isEdit ? 'Edit app' : 'Create app'}</Typography>
      <Typography color="text.secondary" sx={{ mb: 3 }}>
        Define the namespace that owns endpoint routes and handler references.
      </Typography>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      <Card variant="outlined">
        <CardContent sx={{ display: 'grid', gap: 1 }}>
          <TextField fullWidth label="Name *" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} required margin="normal" />
          <TextField fullWidth label="Slug *" value={form.slug} onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))} required margin="normal" disabled={isEdit} helperText={isEdit ? 'Read-only after create; duplicate the app to start from a copied namespace.' : 'Lowercase URL-safe identifier, for example campus-card'} />
          <TextField fullWidth label="Description" value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} margin="normal" multiline minRows={2} />
          <TextField fullWidth label="Handler source path" value={form.app_path} onChange={(e) => setForm((f) => ({ ...f, app_path: e.target.value }))} margin="normal" placeholder="/path/to/handlers" helperText="Directory path where this app's handler code lives" />
          <FormControlLabel control={<Switch checked={form.enabled} onChange={(e) => setForm((f) => ({ ...f, enabled: e.target.checked }))} />} label="Enabled for routing" sx={{ display: 'block', mt: 1 }} />
        </CardContent>
      </Card>
      <Box sx={{ mt: 2, display: 'flex', gap: 1, flexWrap: 'wrap' }}>
        <Button type="submit" variant="contained" disabled={saving} startIcon={isEdit ? <SaveIcon /> : <AddIcon />}>{isEdit ? 'Save changes' : 'Create app'}</Button>
        <Button component={Link} to={isEdit ? `/apps/${appId}` : '/'}>Cancel</Button>
      </Box>
    </Box>
  );
}
