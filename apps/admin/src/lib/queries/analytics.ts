import { useQuery } from '@tanstack/react-query';

import { AnalyticsParams, fetchAnalytics } from '@/lib/api/analytics';

export function useAnalyticsQuery(params: AnalyticsParams) {
  return useQuery({
    queryKey: ['analytics', params.from, params.to, params.status],
    queryFn: () => fetchAnalytics(params),
    // Keep the previous report on screen while a new range loads.
    placeholderData: (prev) => prev,
  });
}
