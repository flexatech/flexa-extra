import { __ } from '@wordpress/i18n';

import { api, handleResponse } from '@/lib/api/base';
import { OptionSet, OptionSetSummary } from '@/lib/schema/option-set';

/** Payload sent on create/update — the server sanitizes it authoritatively. */
export type OptionSetInput = Omit<OptionSet, 'id'>;

export async function fetchOptionSets(): Promise<OptionSetSummary[]> {
  const response = await api.get('option-sets');
  const result = await handleResponse<{ items: OptionSetSummary[] }>(
    response,
    __('Failed to load option sets', 'flexa-extra'),
  );
  return result.data?.items ?? [];
}

export async function fetchOptionSet(id: number): Promise<OptionSetSummary> {
  const response = await api.get(`option-sets/${id}`);
  const result = await handleResponse<OptionSetSummary>(
    response,
    __('Failed to load option set', 'flexa-extra'),
  );
  if (!result.data) {
    throw new Error(__('Option set not found', 'flexa-extra'));
  }
  return result.data;
}

export async function createOptionSet(input: OptionSetInput): Promise<OptionSetSummary> {
  const response = await api.post('option-sets', { json: input });
  const result = await handleResponse<OptionSetSummary>(
    response,
    __('Failed to create option set', 'flexa-extra'),
  );
  return result.data as OptionSetSummary;
}

export async function updateOptionSet(
  id: number,
  input: OptionSetInput,
): Promise<OptionSetSummary> {
  const response = await api.put(`option-sets/${id}`, { json: input });
  const result = await handleResponse<OptionSetSummary>(
    response,
    __('Failed to save option set', 'flexa-extra'),
  );
  return result.data as OptionSetSummary;
}

export async function deleteOptionSet(id: number): Promise<void> {
  const response = await api.delete(`option-sets/${id}`);
  await handleResponse(response, __('Failed to delete option set', 'flexa-extra'));
}
