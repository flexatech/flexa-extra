import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { AlertTriangle, DownloadCloud, Loader2, PackageOpen } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ImportResult, MigrationSource } from '@/lib/api/migration';
import { useImportFromSourceMutation, useMigrationSourcesQuery } from '@/lib/queries/migration';

export default function Migration() {
  const navigate = useNavigate();
  const { data: sources, isLoading, isError, error } = useMigrationSourcesQuery();
  const importMutation = useImportFromSourceMutation();
  const [results, setResults] = useState<Record<string, ImportResult>>({});
  const [pending, setPending] = useState<string | null>(null);

  const runImport = (source: MigrationSource) => {
    const confirmed = window.confirm(
      sprintf(
        /* translators: %s: source plugin name. */
        __('Import %s option sets into Flexa Extra? They are added switched off so you can review them before turning them on.', 'flexa-extra'),
        source.label,
      ),
    );
    if (!confirmed) {
      return;
    }
    setPending(source.slug);
    importMutation.mutate(source.slug, {
      onSuccess: (result) => setResults((prev) => ({ ...prev, [source.slug]: result })),
      onSettled: () => setPending(null),
    });
  };

  return (
    <div className="mx-auto mt-8 max-w-4xl space-y-6 px-6 pb-16">
      <div>
        <h1 className="flex items-center gap-2 text-xl font-semibold">
          <DownloadCloud className="h-5 w-5" />
          {__('Import from another plugin', 'flexa-extra')}
        </h1>
        <p className="text-muted-foreground mt-1 text-sm">
          {__(
            'Bring your existing option sets over from another plugin. Each one is added switched off, so nothing changes on your storefront until you review it and turn it on.',
            'flexa-extra',
          )}
        </p>
      </div>

      {isLoading && (
        <div className="text-muted-foreground flex items-center gap-2 text-sm">
          <Loader2 className="h-4 w-4 animate-spin" />
          {__('Checking for importable data...', 'flexa-extra')}
        </div>
      )}

      {isError && (
        <div className="border-destructive/40 bg-destructive/5 text-destructive rounded-lg border p-4 text-sm">
          {(error as Error)?.message ?? __('Failed to load import sources.', 'flexa-extra')}
        </div>
      )}

      {!isLoading && !isError && (sources ?? []).length === 0 && (
        <div className="border-border text-muted-foreground flex items-center gap-2 rounded-lg border border-dashed p-6 text-sm">
          <PackageOpen className="h-4 w-4 shrink-0" />
          {__(
            'No supported plugins were found on this site. Import sources appear here only once a plugin like YayExtra or ThemeHigh "Extra Product Options" has been installed and has data to bring over.',
            'flexa-extra',
          )}
        </div>
      )}

      <div className="space-y-4">
        {(sources ?? []).map((source) => {
          const busy = pending === source.slug;
          const result = results[source.slug];
          const empty = !source.available || source.count === 0;

          return (
            <div key={source.slug} className="border-border rounded-lg border p-5">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <h2 className="text-foreground font-semibold">{source.label}</h2>
                  <p className="text-muted-foreground mt-1 text-sm">
                    {source.available
                      ? sprintf(
                          /* translators: %d: number of option sets found. */
                          _n('%d option set found', '%d option sets found', source.count, 'flexa-extra'),
                          source.count,
                        )
                      : __('No data found on this site.', 'flexa-extra')}
                  </p>
                </div>
                <Button onClick={() => runImport(source)} disabled={empty || busy}>
                  {busy ? (
                    <>
                      <Loader2 className="h-4 w-4 animate-spin" />
                      {__('Importing...', 'flexa-extra')}
                    </>
                  ) : (
                    <>
                      <DownloadCloud className="h-4 w-4" />
                      {__('Import', 'flexa-extra')}
                    </>
                  )}
                </Button>
              </div>

              {result && <ImportReport result={result} onOpenList={() => navigate('/option-sets')} />}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function ImportReport({ result, onOpenList }: { result: ImportResult; onOpenList: () => void }) {
  const nothing = result.imported.length === 0;

  return (
    <div className="border-border mt-4 space-y-3 border-t pt-4">
      {nothing ? (
        <p className="text-muted-foreground flex items-center gap-2 text-sm">
          <PackageOpen className="h-4 w-4" />
          {__('Nothing was imported.', 'flexa-extra')}
          {result.skipped > 0 &&
            ' ' +
              sprintf(
                /* translators: %d: number of sets skipped. */
                _n('%d set was skipped (no importable fields).', '%d sets were skipped (no importable fields).', result.skipped, 'flexa-extra'),
                result.skipped,
              )}
        </p>
      ) : (
        <>
          <p className="text-foreground text-sm font-medium">
            {sprintf(
              /* translators: %d: number of option sets imported. */
              _n('%d option set added (switched off):', '%d option sets added (switched off):', result.imported.length, 'flexa-extra'),
              result.imported.length,
            )}
          </p>
          <ul className="space-y-2">
            {result.imported.map((item) => (
              <li key={item.id} className="text-sm">
                <span className="text-foreground font-medium">{item.name}</span>
                {item.warnings.length > 0 && (
                  <ul className="mt-1 space-y-1">
                    {item.warnings.map((w, i) => (
                      <li
                        key={i}
                        className={cn('text-muted-foreground flex items-start gap-1.5 text-xs')}
                      >
                        <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0 text-amber-500" />
                        <span>{w}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </li>
            ))}
          </ul>
          {result.skipped > 0 && (
            <p className="text-muted-foreground text-xs">
              {sprintf(
                /* translators: %d: number of sets skipped. */
                _n('%d set was skipped (no importable fields).', '%d sets were skipped (no importable fields).', result.skipped, 'flexa-extra'),
                result.skipped,
              )}
            </p>
          )}
          <Button variant="outline" size="sm" onClick={onOpenList}>
            {__('Review in Option Sets', 'flexa-extra')}
          </Button>
        </>
      )}
    </div>
  );
}
