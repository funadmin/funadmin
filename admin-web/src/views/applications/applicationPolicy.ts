import type { EnterpriseApplication } from '@/api/identity/applications';

export const canLaunchApplication = (application: Pick<EnterpriseApplication, 'status'> & { launchUrl?: string; launch_url?: string }) =>
  application.status === 'published' && Boolean(application.launchUrl || application.launch_url);
