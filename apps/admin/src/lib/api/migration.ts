import { __ } from '@wordpress/i18n';

import { api, handleResponse } from '@/lib/api/base';

export interface MigrationSource {
  slug: string;
  label: string;
  available: boolean;
  count: number;
}

export interface ImportedItem {
  id: number;
  name: string;
  warnings: string[];
}

export interface ImportResult {
  source: string;
  label: string;
  imported: ImportedItem[];
  skipped: number;
  message?: string;
}

export async function fetchMigrationSources(): Promise<MigrationSource[]> {
  const response = await api.get('migration/sources');
  const result = await handleResponse<{ sources: MigrationSource[] }>(
    response,
    __('Failed to load import sources', 'flexa-extra'),
  );
  return result.data?.sources ?? [];
}

export async function importFromSource(slug: string): Promise<ImportResult> {
  const response = await api.post('migration/import', { json: { source: slug } });
  const result = await handleResponse<ImportResult>(
    response,
    __('Import failed', 'flexa-extra'),
  );
  return { ...(result.data as ImportResult), message: result.message };
}
