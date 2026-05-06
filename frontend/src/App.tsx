import { Routes, Route, Link } from 'react-router-dom';
import { AppBar, Box, Button, Container, Toolbar, Typography } from '@mui/material';
import AccountTreeIcon from '@mui/icons-material/AccountTree';
import AppsIcon from '@mui/icons-material/Apps';
import ScienceIcon from '@mui/icons-material/Science';
import AppsList from './pages/AppsList';
import AppDetail from './pages/AppDetail';
import AppForm from './pages/AppForm';
import EndpointDetail from './pages/EndpointDetail';
import EndpointForm from './pages/EndpointForm';
import Tester from './pages/Tester';

function App() {
  return (
    <>
      <AppBar
        position="sticky"
        elevation={0}
        sx={{
          bgcolor: 'rgba(8, 13, 18, 0.78)',
          borderBottom: '1px solid',
          borderColor: 'divider',
          backdropFilter: 'blur(18px)',
        }}
      >
        <Toolbar sx={{ minHeight: 68, gap: 1.5 }}>
          <Box
            component={Link}
            to="/"
            sx={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: 1,
              color: 'inherit',
              textDecoration: 'none',
              mr: 'auto',
            }}
          >
            <Box
              sx={{
                width: 34,
                height: 34,
                display: 'grid',
                placeItems: 'center',
                borderRadius: 0,
                color: 'primary.main',
                bgcolor: 'rgba(88, 166, 255, 0.12)',
                border: '1px solid rgba(88, 166, 255, 0.28)',
              }}
            >
              <AccountTreeIcon fontSize="small" />
            </Box>
            <Box>
              <Typography variant="subtitle1" fontWeight={800} lineHeight={1.1}>
                Switchboard
              </Typography>
              <Typography variant="caption" color="text.secondary">
                Operator console
              </Typography>
            </Box>
          </Box>
          <Button color="inherit" startIcon={<AppsIcon />} component={Link} to="/">Apps</Button>
          <Button color="inherit" startIcon={<ScienceIcon />} component={Link} to="/tester">Endpoint tester</Button>
        </Toolbar>
      </AppBar>
      <Container maxWidth="lg" sx={{ mt: 4, mb: 6 }}>
        <Routes>
          <Route path="/" element={<AppsList />} />
          <Route path="/apps/new" element={<AppForm />} />
          <Route path="/apps/:appId" element={<AppDetail />} />
          <Route path="/apps/:appId/edit" element={<AppForm />} />
          <Route path="/apps/:appId/endpoints/new" element={<EndpointForm />} />
          <Route path="/endpoints/:endpointId" element={<EndpointDetail />} />
          <Route path="/endpoints/:endpointId/edit" element={<EndpointForm />} />
          <Route path="/tester" element={<Tester />} />
        </Routes>
      </Container>
    </>
  );
}

export default App;
