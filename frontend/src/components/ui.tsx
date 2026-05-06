import { type ReactNode } from 'react';
import { Box, IconButton, Tooltip } from '@mui/material';
import type { SxProps, Theme } from '@mui/material/styles';

interface TagProps {
  children: ReactNode;
  tone?: 'default' | 'primary' | 'success' | 'danger';
  sx?: SxProps<Theme>;
}

export function Tag({ children, tone = 'default', sx }: TagProps) {
  const colors = {
    default: { color: 'text.secondary', borderColor: 'divider', bgcolor: 'rgba(139, 148, 158, 0.06)' },
    primary: { color: 'primary.light', borderColor: 'rgba(88, 166, 255, 0.35)', bgcolor: 'rgba(88, 166, 255, 0.08)' },
    success: { color: 'success.main', borderColor: 'rgba(86, 211, 100, 0.35)', bgcolor: 'rgba(86, 211, 100, 0.08)' },
    danger: { color: 'error.main', borderColor: 'rgba(255, 123, 114, 0.35)', bgcolor: 'rgba(255, 123, 114, 0.08)' },
  }[tone];

  return (
    <Box
      component="span"
      sx={{
        display: 'inline-flex',
        alignItems: 'center',
        minHeight: 24,
        px: 0.75,
        border: '1px solid',
        borderRadius: 0,
        fontSize: '0.72rem',
        fontWeight: 800,
        lineHeight: 1,
        ...colors,
        ...sx,
      }}
    >
      {children}
    </Box>
  );
}

interface ActionIconProps {
  label: string;
  children: ReactNode;
  color?: 'primary' | 'error' | 'inherit';
  component?: React.ElementType;
  to?: string;
  onClick?: () => void;
}

export function ActionIcon({ label, children, color = 'primary', component, to, onClick }: ActionIconProps) {
  const sx = {
    width: 34,
    height: 34,
    border: '1px solid',
    borderColor: color === 'error' ? 'rgba(255, 123, 114, 0.32)' : 'rgba(88, 166, 255, 0.24)',
    borderRadius: 0,
    bgcolor: color === 'error' ? 'rgba(255, 123, 114, 0.06)' : 'rgba(88, 166, 255, 0.06)',
  };

  if (component) {
    const ComponentIconButton = IconButton as React.ElementType;
    return (
      <Tooltip title={label}>
        <ComponentIconButton
          aria-label={label}
          color={color}
          component={component}
          to={to}
          size="small"
          sx={sx}
        >
          {children}
        </ComponentIconButton>
      </Tooltip>
    );
  }

  return (
    <Tooltip title={label}>
      <IconButton
        aria-label={label}
        color={color}
        onClick={onClick}
        size="small"
        sx={sx}
      >
        {children}
      </IconButton>
    </Tooltip>
  );
}
