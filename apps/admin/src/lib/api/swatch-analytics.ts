import { __ } from '@wordpress/i18n';

import { api, handleResponse } from '@/lib/api/base';

export interface SwatchAnalyticsTerm {
  taxonomy: string;
  attribute_label: string;
  slug: string;
  name: string;
  swatch_type: string;
  color: string;
  image: string;
  count: number;
  revenue: number;
}

export interface SwatchAnalyticsAttribute {
  taxonomy: string;
  label: string;
  swatch_type: string;
  count: number;
  revenue: number;
}

export interface SwatchAnalyticsReport {
  range: { from: string; to: string };
  totals: { orders: number; selections: number; revenue: number };
  attributes: SwatchAnalyticsAttribute[];
  terms: SwatchAnalyticsTerm[];
  scanned: { orders: number; capped: boolean; max: number };
}

export interface SwatchAnalyticsParams {
  from: string;
  to: string;
  status: string;
}

const EMPTY: SwatchAnalyticsReport = {
  range: { from: '', to: '' },
  totals: { orders: 0, selections: 0, revenue: 0 },
  attributes: [],
  terms: [],
  scanned: { orders: 0, capped: false, max: 0 },
};

export async function fetchSwatchAnalytics(
  params: SwatchAnalyticsParams,
): Promise<SwatchAnalyticsReport> {
  const response = await api.get('variation-swatches/analytics', {
    searchParams: { from: params.from, to: params.to, status: params.status },
  });
  const result = await handleResponse<SwatchAnalyticsReport>(
    response,
    __('Failed to load swatch analytics', 'flexa-extra'),
  );
  return result.data ?? EMPTY;
}
