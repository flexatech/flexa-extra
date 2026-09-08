import { __ } from '@wordpress/i18n';

import { api, handleResponse } from '@/lib/api/base';

export interface AnalyticsOption {
  field: string;
  field_label: string;
  option: string;
  name: string;
  type: string;
  count: number;
  revenue: number;
}

export interface AnalyticsField {
  field: string;
  label: string;
  type: string;
  count: number;
  revenue: number;
}

export interface AnalyticsReport {
  range: { from: string; to: string };
  totals: { orders: number; selections: number; revenue: number };
  options: AnalyticsOption[];
  fields: AnalyticsField[];
  scanned: { orders: number; capped: boolean; max: number };
}

export interface AnalyticsParams {
  from: string;
  to: string;
  status: string;
}

const EMPTY: AnalyticsReport = {
  range: { from: '', to: '' },
  totals: { orders: 0, selections: 0, revenue: 0 },
  options: [],
  fields: [],
  scanned: { orders: 0, capped: false, max: 0 },
};

export async function fetchAnalytics(params: AnalyticsParams): Promise<AnalyticsReport> {
  const response = await api.get('analytics', {
    searchParams: { from: params.from, to: params.to, status: params.status },
  });
  const result = await handleResponse<AnalyticsReport>(
    response,
    __('Failed to load analytics', 'flexa-extra'),
  );
  return result.data ?? EMPTY;
}
