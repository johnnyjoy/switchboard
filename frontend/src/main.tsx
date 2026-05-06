import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import CssBaseline from '@mui/material/CssBaseline';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import App from './App';

const theme = createTheme({
  palette: {
    mode: 'dark',
    primary: { main: '#58a6ff', light: '#8bc4ff', dark: '#1f6feb' },
    success: { main: '#56d364' },
    warning: { main: '#d29922' },
    error: { main: '#ff7b72' },
    background: { default: '#080d12', paper: '#121a24' },
    divider: 'rgba(139, 148, 158, 0.18)',
  },
  shape: { borderRadius: 4 },
  typography: {
    fontFamily: [
      'Inter',
      'ui-sans-serif',
      'system-ui',
      '-apple-system',
      'BlinkMacSystemFont',
      '"Segoe UI"',
      'sans-serif',
    ].join(','),
    h5: { fontWeight: 750, letterSpacing: '-0.02em' },
    h6: { fontWeight: 700, letterSpacing: '-0.01em' },
    button: { fontWeight: 700, letterSpacing: '0.02em' },
  },
  components: {
    MuiCssBaseline: {
      styleOverrides: {
        body: {
          background: 'linear-gradient(180deg, #0d141d 0%, #080d12 34rem)',
        },
        code: {
          fontFamily: '"SFMono-Regular", Consolas, "Liberation Mono", monospace',
          color: '#c9d1d9',
        },
      },
    },
    MuiPaper: {
      styleOverrides: {
        root: {
          backgroundImage: 'none',
          borderColor: 'rgba(139, 148, 158, 0.18)',
        },
      },
    },
    MuiCard: {
      styleOverrides: {
        root: {
          backgroundImage: 'linear-gradient(180deg, rgba(255,255,255,0.035), rgba(255,255,255,0.015))',
          borderColor: 'rgba(139, 148, 158, 0.18)',
          boxShadow: '0 18px 60px rgba(0, 0, 0, 0.24)',
        },
      },
    },
    MuiButton: {
      styleOverrides: {
        root: { borderRadius: 2, textTransform: 'none' },
      },
    },
    MuiIconButton: {
      styleOverrides: {
        root: { borderRadius: 0 },
      },
    },
    MuiTableCell: {
      styleOverrides: {
        head: {
          color: '#8b949e',
          fontSize: '0.72rem',
          fontWeight: 800,
          letterSpacing: '0.08em',
          textTransform: 'uppercase',
        },
      },
    },
  },
});

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <App />
      </BrowserRouter>
    </ThemeProvider>
  </React.StrictMode>
);
