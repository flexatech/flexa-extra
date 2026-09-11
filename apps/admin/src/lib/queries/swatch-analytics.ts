import { useQuery } from '@tanstack/react-query';

import { SwatchAnalyticsParams, fetchSwatchAnalytics } from '@/lib/api/swatch-analytics';

export function useSwatchAnalyticsQuery(params: SwatchAnalyticsParams) {
  return useQuery({
    queryKey: ['swatch-analytics', params.from, params.to, params.status],
    queryFn: () => fetchSwatchAnalytics(params),
    // Keep the previous report on screen while a new range loads.
    placeholderData: (prev) => prev,
  });
}
