import { OptionSetSummary } from '@/lib/schema/option-set';

/**
 * Portable envelope for exported option sets. The server accepts this shape (or a
 * bare set / bare list) on import; `id` is dropped so imported sets are created
 * fresh rather than overwriting an existing post.
 */
export interface OptionSetEnvelope {
  plugin: 'flexa-extra';
  type: 'option-sets';
  version: 1;
  items: Array<Pick<OptionSetSummary, 'name' | 'status' | 'fields' | 'targeting'>>;
}

export function buildEnvelope(sets: OptionSetSummary[]): OptionSetEnvelope {
  return {
    plugin: 'flexa-extra',
    type: 'option-sets',
    version: 1,
    items: sets.map(({ name, status, fields, targeting }) => ({ name, status, fields, targeting })),
  };
}

/** Slug-safe filename fragment from an option set name. */
function slugify(value: string): string {
  return (
    value
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 60) || 'option-sets'
  );
}

export function downloadEnvelope(sets: OptionSetSummary[], filenameBase?: string): void {
  const envelope = buildEnvelope(sets);
  const base = filenameBase ?? (sets.length === 1 ? slugify(sets[0].name) : 'option-sets');
  const blob = new Blob([JSON.stringify(envelope, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `flexa-extra-${base}.json`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

/** Read a user-selected file and parse it as JSON. Throws on invalid JSON. */
export async function readJsonFile(file: File): Promise<unknown> {
  const text = await file.text();
  return JSON.parse(text) as unknown;
}
