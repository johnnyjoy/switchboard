import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 3083,
    proxy: {
      '/api': {
        // PHP backend (scripts/php-router.php) default port 8080. Override with CONTROL_PLANE_URL if needed.
        target: process.env.CONTROL_PLANE_URL || 'http://127.0.0.1:8080',
        changeOrigin: true,
      },
    },
  },
});
