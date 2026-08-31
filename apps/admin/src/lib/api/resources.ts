import { __ } from '@wordpress/i18n';

import { api, handleResponse } from '@/lib/api/base';

export type ResourceType = 'product' | 'category' | 'tag';

export interface ResourceItem {
  id: number;
  label: string;
}

export async function searchResources(type: ResourceType, q: string): Promise<ResourceItem[]> {
  const response = await api.get('search', { searchParams: { type, q } });
  const result = await handleResponse<{ items: ResourceItem[] }>(
    response,
    __('Search failed', 'flexa-extra'),
  );
  return result.data?.items ?? [];
}

export async function resolveResources(type: ResourceType, ids: number[]): Promise<ResourceItem[]> {
  if (ids.length === 0) return [];
  const response = await api.get('resolve', { searchParams: { type, ids: ids.join(',') } });
  const result = await handleResponse<{ items: ResourceItem[] }>(
    response,
    __('Lookup failed', 'flexa-extra'),
  );
  return result.data?.items ?? [];
}
