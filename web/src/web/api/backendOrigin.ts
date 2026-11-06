import { APP_CONFIG } from '@/config';

// The administrator API includes /admin; public APIs share its server origin.
export const backendOrigin = /^https?:\/\//i.test(APP_CONFIG.baseApi)
  ? new URL(APP_CONFIG.baseApi).origin
  : '';
