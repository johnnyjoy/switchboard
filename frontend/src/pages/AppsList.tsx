import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Alert,
  Box,
  Button,
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
import AddIcon from '@mui/icons-material/Add';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import OpenInNewIcon from '@mui/icons-material/OpenInNew';
import RouteIcon from '@mui/icons-material/Route';
import { api, type App, type Endpoint } from '../api';
import { ActionIcon, Tag } from '../components/ui';

export default function AppsList() {
  const [apps, setApps] = useState<App[]>([]);
  const [endpoints, setEndpoints] = useState<Endpoint[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const navigate = useNavigate();

  const loadApps = () => {
    setActionError(null);
    Promise.all([api.getApps(), api.getEndpoints()])
      .then(([loadedApps, loadedEndpoints]) => {
        setApps(loadedApps);
        setEndpoints(loadedEndpoints);
      })
      .catch((e) => setError(String(e.message)))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadApps();
  }, []);

  const handleCopy = async (app: App) => {
    setActionError(null);
    try {
      const copied = await api.copyApp(app.id);
      navigate(`/apps/${copied.id}`);
    } catch (err) {
      setActionError(err instanceof Error ? err.message : String(err));
    }
  };

  const handleDelete = async (app: App) => {
    if (!window.confirm(`Delete "${app.name}" and all of its endpoints? This cannot be undone.`)) {
      return;
    }
    setActionError(null);
    try {
      await api.deleteApp(app.id);
      loadApps();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : String(err));
    }
  };

  if (error) {
    const isBackendUnreachable = error.includes('Backend unreachable');
    return (
      <Box sx={{ mt: 2 }}>
        <Typography color="error" sx={{ mb: 1 }}>
          {error}
        </Typography>
        {isBackendUnreachable && (
          <Typography variant="body2" color="text.secondary" component="pre" sx={{ whiteSpace: 'pre-wrap', fontFamily: 'monospace', bgcolor: 'action.hover', p: 2, borderRadius: 0 }}>
            Terminal 1: php -S localhost:8080 -t . scripts/php-router.php{'\n'}
            Terminal 2: pnpm dev
          </Typography>
        )}
      </Box>
    );
  }
  if (loading) return <Typography>Loading…</Typography>;
  const enabledApps = apps.filter((app) => app.enabled).length;
  const endpointCountFor = (appId: string) => endpoints.filter((endpoint) => endpoint.app_id === appId).length;

  return (
    <Box>
      <Box
        sx={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: { xs: 'flex-start', sm: 'center' },
          gap: 2,
          mb: 3,
          flexDirection: { xs: 'column', sm: 'row' },
        }}
      >
        <Box>
          <Typography variant="overline" color="primary.light" fontWeight={800}>Control plane</Typography>
          <Typography variant="h5">Applications</Typography>
          <Typography color="text.secondary" sx={{ mt: 0.75 }}>
            Claim app namespaces, route traffic, and manage endpoint ownership.
          </Typography>
        </Box>
        <Button variant="contained" size="large" startIcon={<AddIcon />} component={Link} to="/apps/new">
          Create app
        </Button>
      </Box>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', sm: 'repeat(3, 1fr)' }, gap: 2, mb: 3 }}>
        <Card variant="outlined">
          <CardContent sx={{ pb: '16px !important' }}>
            <Typography variant="body2" color="text.secondary">Applications</Typography>
            <Typography variant="h5">{apps.length}</Typography>
          </CardContent>
        </Card>
        <Card variant="outlined">
          <CardContent sx={{ pb: '16px !important' }}>
            <Typography variant="body2" color="text.secondary">Enabled</Typography>
            <Typography variant="h5" color="success.main">{enabledApps}</Typography>
          </CardContent>
        </Card>
        <Card variant="outlined">
          <CardContent sx={{ pb: '16px !important' }}>
            <Typography variant="body2" color="text.secondary">Endpoints</Typography>
            <Typography variant="h5">{endpoints.length}</Typography>
          </CardContent>
        </Card>
      </Box>

      {actionError && <Alert severity="error" sx={{ mb: 2 }}>{actionError}</Alert>}
      {apps.length === 0 ? (
        <Paper variant="outlined" sx={{ p: 3, textAlign: 'center' }}>
          <RouteIcon color="primary" sx={{ fontSize: 36, mb: 1 }} />
          <Typography variant="subtitle1" fontWeight={600}>No apps yet</Typography>
          <Typography color="text.secondary" sx={{ mb: 2 }}>Create an app to start claiming routes and testing endpoints.</Typography>
          <Button variant="contained" startIcon={<AddIcon />} component={Link} to="/apps/new">Create app</Button>
        </Paper>
      ) : (
        <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 0, overflow: 'hidden' }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Name</TableCell>
                <TableCell>Slug</TableCell>
                <TableCell>Endpoints</TableCell>
                <TableCell>Status</TableCell>
                <TableCell align="right">Actions</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {apps.map((app) => (
                <TableRow key={app.id} hover sx={{ '&:last-child td': { borderBottom: 0 } }}>
                  <TableCell>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25 }}>
                      <Box
                        sx={{
                          width: 34,
                          height: 34,
                          display: 'grid',
                          placeItems: 'center',
                          borderRadius: 0,
                          color: 'primary.main',
                          bgcolor: 'rgba(88, 166, 255, 0.1)',
                        }}
                      >
                        <RouteIcon fontSize="small" />
                      </Box>
                      <Box>
                        <Typography component={Link} to={`/apps/${app.id}`} sx={{ color: 'text.primary', fontWeight: 750, textDecoration: 'none', '&:hover': { color: 'primary.light' } }}>
                          {app.name}
                        </Typography>
                        {app.description && (
                          <Typography variant="caption" color="text.secondary" display="block" sx={{ maxWidth: 380, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {app.description}
                          </Typography>
                        )}
                      </Box>
                    </Box>
                  </TableCell>
                  <TableCell><Tag>{app.slug}</Tag></TableCell>
                  <TableCell>
                    <Tag>{endpointCountFor(app.id)} routes</Tag>
                  </TableCell>
                  <TableCell>
                    <Tag tone={app.enabled ? 'success' : 'default'}>{app.enabled ? 'Enabled' : 'Disabled'}</Tag>
                  </TableCell>
                  <TableCell align="right">
                    <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 0.75, flexWrap: 'wrap' }}>
                      <ActionIcon label={`Open ${app.name}`} component={Link} to={`/apps/${app.id}`}>
                        <OpenInNewIcon fontSize="small" />
                      </ActionIcon>
                      <ActionIcon label={`Edit ${app.name}`} component={Link} to={`/apps/${app.id}/edit`}>
                        <EditIcon fontSize="small" />
                      </ActionIcon>
                      <ActionIcon label={`Copy ${app.name}`} onClick={() => handleCopy(app)}>
                        <ContentCopyIcon fontSize="small" />
                      </ActionIcon>
                      <ActionIcon label={`Delete ${app.name}`} color="error" onClick={() => handleDelete(app)}>
                        <DeleteIcon fontSize="small" />
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
