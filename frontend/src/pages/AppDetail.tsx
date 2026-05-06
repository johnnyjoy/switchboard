import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Paper,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import OpenInNewIcon from '@mui/icons-material/OpenInNew';
import PowerSettingsNewIcon from '@mui/icons-material/PowerSettingsNew';
import RouteIcon from '@mui/icons-material/Route';
import ScienceIcon from '@mui/icons-material/Science';
import { api, type App, type Endpoint } from '../api';
import { ActionIcon, Tag } from '../components/ui';

export default function AppDetail() {
  const { appId } = useParams<{ appId: string }>();
  const [app, setApp] = useState<App | null>(null);
  const [endpoints, setEndpoints] = useState<Endpoint[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const navigate = useNavigate();

  const loadData = () => {
    if (!appId) return;
    Promise.all([api.getApps(), api.getEndpoints(appId)])
      .then(([apps, eps]) => {
        const a = apps.find((x) => x.id === appId);
        setApp(a ?? null);
        setEndpoints(eps);
      })
      .catch((e) => setError(String(e.message)))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, [appId]);

  const handleCopy = async () => {
    if (!app) return;
    setActionError(null);
    try {
      const copied = await api.copyApp(app.id);
      navigate(`/apps/${copied.id}`);
    } catch (err) {
      setActionError(err instanceof Error ? err.message : String(err));
    }
  };

  const handleDelete = async () => {
    if (!app) return;
    if (!window.confirm(`Delete "${app.name}" and all of its endpoints? This cannot be undone.`)) {
      return;
    }
    setActionError(null);
    try {
      await api.deleteApp(app.id);
      navigate('/');
    } catch (err) {
      setActionError(err instanceof Error ? err.message : String(err));
    }
  };

  const handleCopyEndpoint = async (endpoint: Endpoint) => {
    setActionError(null);
    try {
      const copied = await api.copyEndpoint(endpoint.id);
      navigate(`/endpoints/${copied.id}`);
    } catch (err) {
      setActionError(err instanceof Error ? err.message : String(err));
    }
  };

  const handleDeleteEndpoint = async (endpoint: Endpoint) => {
    if (!window.confirm(`Delete "${endpoint.name}"? This cannot be undone.`)) {
      return;
    }
    setActionError(null);
    try {
      await api.deleteEndpoint(endpoint.id);
      loadData();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : String(err));
    }
  };

  const handleToggleEndpoint = async (endpoint: Endpoint) => {
    const nextEnabled = !endpoint.enabled;
    setActionError(null);
    setEndpoints((current) =>
      current.map((item) => (item.id === endpoint.id ? { ...item, enabled: nextEnabled } : item))
    );
    try {
      await api.updateEndpoint(endpoint.id, { enabled: nextEnabled });
    } catch (err) {
      setEndpoints((current) =>
        current.map((item) => (item.id === endpoint.id ? { ...item, enabled: endpoint.enabled } : item))
      );
      setActionError(err instanceof Error ? err.message : String(err));
    }
  };

  if (error) return <Typography color="error">{error}</Typography>;
  if (loading || !app) return <Typography>Loading…</Typography>;
  const enabledEndpoints = endpoints.filter((endpoint) => endpoint.enabled).length;

  return (
    <Box>
      <Card variant="outlined" sx={{ mb: 3, overflow: 'hidden' }}>
        <CardContent sx={{ p: { xs: 2.5, md: 3 } }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: { xs: 'flex-start', md: 'center' }, gap: 2, flexDirection: { xs: 'column', md: 'row' } }}>
            <Box>
              <Box sx={{ display: 'flex', alignItems: 'baseline', gap: 1.25, flexWrap: 'wrap' }}>
                <Typography variant="overline" color="primary.light" fontWeight={800}>Application</Typography>
                <Typography variant="h5">{app.name}</Typography>
              </Box>
              <Box sx={{ mt: 1, display: 'flex', gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
                <Tag tone={app.enabled ? 'success' : 'default'}><PowerSettingsNewIcon sx={{ fontSize: 14, mr: 0.5 }} />{app.enabled ? 'Enabled' : 'Disabled'}</Tag>
                <Tag><RouteIcon sx={{ fontSize: 14, mr: 0.5 }} />{endpoints.length} routes</Tag>
              </Box>
            </Box>
            <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap', justifyContent: { xs: 'flex-start', md: 'flex-end' } }}>
              <ActionIcon label={`Edit ${app.name}`} component={Link} to={`/apps/${app.id}/edit`}>
                <EditIcon fontSize="small" />
              </ActionIcon>
              <ActionIcon label={`Copy ${app.name}`} onClick={handleCopy}>
                <ContentCopyIcon fontSize="small" />
              </ActionIcon>
              <ActionIcon label={`Delete ${app.name}`} color="error" onClick={handleDelete}>
                <DeleteIcon fontSize="small" />
              </ActionIcon>
              <Button variant="contained" startIcon={<AddIcon />} component={Link} to={`/apps/${app.id}/endpoints/new`}>
                Create endpoint
              </Button>
            </Box>
          </Box>
        </CardContent>
      </Card>
      {actionError && <Alert severity="error" sx={{ mb: 2 }}>{actionError}</Alert>}

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' }, gap: 2, mb: 3 }}>
        <Card variant="outlined">
          <CardContent sx={{ pb: '16px !important' }}>
            <Typography variant="body2" color="text.secondary">Total endpoints</Typography>
            <Typography variant="h5">{endpoints.length}</Typography>
          </CardContent>
        </Card>
        <Card variant="outlined">
          <CardContent sx={{ pb: '16px !important' }}>
            <Typography variant="body2" color="text.secondary">Enabled endpoints</Typography>
            <Typography variant="h5" color="success.main">{enabledEndpoints}</Typography>
          </CardContent>
        </Card>
      </Box>

      <Typography variant="h6" sx={{ mt: 3, mb: 1 }}>Endpoints</Typography>
      {endpoints.length === 0 ? (
        <Paper variant="outlined" sx={{ p: 3, textAlign: 'center' }}>
          <RouteIcon color="primary" sx={{ fontSize: 40, mb: 1 }} />
          <Typography variant="subtitle1" fontWeight={600}>No endpoints yet</Typography>
          <Typography color="text.secondary" sx={{ mb: 2 }}>Create an endpoint to define a route for this app.</Typography>
          <Button variant="contained" startIcon={<AddIcon />} component={Link} to={`/apps/${app.id}/endpoints/new`}>Create endpoint</Button>
        </Paper>
      ) : (
        <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 0, overflow: 'hidden' }}>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Name</TableCell>
                <TableCell>Path</TableCell>
                <TableCell>Methods</TableCell>
                <TableCell>Dispatch target</TableCell>
                <TableCell>Enabled</TableCell>
                <TableCell align="right">Actions</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {endpoints.map((ep) => (
                <TableRow key={ep.id} hover sx={{ '&:last-child td': { borderBottom: 0 } }}>
                  <TableCell>
                    <Link to={`/endpoints/${ep.id}`} style={{ color: 'inherit', fontWeight: 750 }}>
                      {ep.name}
                    </Link>
                  </TableCell>
                  <TableCell><Tag sx={{ fontFamily: 'monospace' }}>{ep.path}</Tag></TableCell>
                  <TableCell><Tag tone="primary">{ep.methods.join(', ')}</Tag></TableCell>
                  <TableCell>
                    <Typography variant="body2"><code>{ep.handler_class}::{ep.handler_method}</code></Typography>
                    <Typography variant="caption" color="text.secondary">
                      PHP class and method invoked after matching
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Switch
                      checked={ep.enabled}
                      onChange={() => handleToggleEndpoint(ep)}
                      size="small"
                      inputProps={{ 'aria-label': `${ep.enabled ? 'Disable' : 'Enable'} ${ep.name}` }}
                      sx={{
                        '& .MuiSwitch-switchBase.Mui-checked': { color: 'success.main' },
                        '& .MuiSwitch-switchBase.Mui-checked + .MuiSwitch-track': { bgcolor: 'success.main' },
                        '& .MuiSwitch-thumb': { borderRadius: 0 },
                        '& .MuiSwitch-track': { borderRadius: 0 },
                      }}
                    />
                  </TableCell>
                  <TableCell align="right">
                    <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 0.75, flexWrap: 'wrap' }}>
                      <ActionIcon label={`Open ${ep.name}`} component={Link} to={`/endpoints/${ep.id}`}>
                        <OpenInNewIcon fontSize="small" />
                      </ActionIcon>
                      <ActionIcon label={`Edit ${ep.name}`} component={Link} to={`/endpoints/${ep.id}/edit`}>
                        <EditIcon fontSize="small" />
                      </ActionIcon>
                      <ActionIcon label={`Copy ${ep.name}`} onClick={() => handleCopyEndpoint(ep)}>
                        <ContentCopyIcon fontSize="small" />
                      </ActionIcon>
                      <ActionIcon label={`Delete ${ep.name}`} color="error" onClick={() => handleDeleteEndpoint(ep)}>
                        <DeleteIcon fontSize="small" />
                      </ActionIcon>
                      <ActionIcon label={`Test ${ep.name}`} component={Link} to={`/tester?endpoint=${ep.id}`}>
                        <ScienceIcon fontSize="small" />
                      </ActionIcon>
                    </Box>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}
    </Box>
  );
}
