import { __ } from '@wordpress/i18n';

import { api, handleResponse } from '@/lib/api/base';

export type SwatchType = 'off' | 'color' | 'image' | 'button';

export interface TermSwatch {
  id: number;
  name: string;
  color: string;
  image: { id: number; url: string };
}

export interface AttributeSwatch {
  taxonomy: string;
  label: string;
  type: SwatchType;
  terms: TermSwatch[];
}

export interface TermUpdate {
  term_id: number;
  color?: string;
  image_id?: number;
}

export async function fetchAttributes(): Promise<AttributeSwatch[]> {
  const response = await api.get('variation-swatches/attributes');
  const result = await handleResponse<{ attributes: AttributeSwatch[] }>(
    response,
    __('Failed to load attributes', 'flexa-extra'),
  );
  return result.data?.attributes ?? [];
}

export async function saveAttributeType(taxonomy: string, type: SwatchType): Promise<SwatchType> {
  const response = await api.post('variation-swatches/attribute', { json: { taxonomy, type } });
  const result = await handleResponse<{ type: SwatchType }>(
    response,
    __('Failed to save attribute', 'flexa-extra'),
  );
  return result.data?.type ?? 'off';
}

export async function saveTerm(update: TermUpdate): Promise<TermSwatch> {
  const response = await api.post('variation-swatches/term', { json: update });
  const result = await handleResponse<TermSwatch>(
    response,
    __('Failed to save term', 'flexa-extra'),
  );
  return (
    result.data ?? {
      id: update.term_id,
      name: '',
      color: update.color ?? '',
      image: { id: update.image_id ?? 0, url: '' },
    }
  );
}
