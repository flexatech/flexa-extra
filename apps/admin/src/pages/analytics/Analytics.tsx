import { useMemo, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { BarChart3, Loader2 } from 'lucide-react';

import { AnalyticsOption } from '@/lib/api/analytics';
import { Select } from '@/components/ui/select';
import { useAnalyticsQuery } from '@/lib/queries/analytics';
import { formatMoney, getCurrency } from '@/lib/preview/engine';
import { cn } from '@/lib/utils';

type RangeKey = '7' | '30' | '90';
type StatusKey = 'paid' | 'completed' | 'any';

const RANGES: { key: RangeKey; label: string }[] = [
  { key: '7', label: __('7 days', 'flexa-extra') },
  { key: '30', label: __('30 days', 'flexa-extra') },
  { key: '90', label: __('90 days', 'flexa-extra') },
];

const STATUSES: { key: StatusKey; label: string; param: string }[] = [
  { key: 'paid', label: __('Paid (processing + completed)', 'flexa-extra'), param: 'completed,processing' },
  { key: 'completed', label: __('Completed only', 'flexa-extra'), param: 'completed' },
  { key: 'any', label: __('Any status', 'flexa-extra'), param: 'any' },
];

/** Local Y-m-d for a date N days before today (inclusive range of N+1 days). */
function ymdDaysAgo(days: number): string {
  const d = new Date();
  d.setDate(d.getDate() - days);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

export default function Analytics() {
  const [range, setRange] = useState<RangeKey>('30');
  const [status, setStatus] = useState<StatusKey>('paid');
  const cur = useMemo(getCurrency, []);

  const params = useMemo(() => {
    const days = Number(range);
    return {
      from: ymdDaysAgo(days - 1),
      to: ymdDaysAgo(0),
      status: STATUSES.find((s) => s.key === status)?.param ?? 'completed,processing',
    };
  }, [range, status]);

  const { data, isLoading, isFetching, isError, error } = useAnalyticsQuery(params);

  const money = (n: number) => formatMoney(n, cur);
  const options = data?.options ?? [];
  const hasData = options.length > 0;

  return (
    <div className="mx-auto mt-8 max-w-7xl space-y-6 px-6 pb-16">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="flex items-center gap-2 text-xl font-semibold">
            <BarChart3 className="h-5 w-5" />
            {__('Option analytics', 'flexa-extra')}
          </h1>
          <p className="text-muted-foreground mt-1 text-sm">
            {__('Which extra options your customers pick, and the revenue they add.', 'flexa-extra')}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <div className="border-border inline-flex overflow-hidden rounded-md border">
            {RANGES.map((r) => (
              <button
                key={r.key}
                type="button"
                onClick={() => setRange(r.key)}
                className={cn(
                  'px-3 py-1.5 text-sm transition-colors',
                  range === r.key
                    ? 'bg-primary text-primary-foreground'
                    : 'hover:bg-muted text-muted-foreground',
                )}
              >
                {r.label}
              </button>
            ))}
          </div>
          <Select
            value={status}
            onChange={(e) => setStatus(e.target.value as StatusKey)}
            className="h-8"
            options={STATUSES.map((s) => ({ label: s.label, value: s.key }))}
          />
          {isFetching && <Loader2 className="text-muted-foreground h-4 w-4 animate-spin" />}
        </div>
      </div>

      {isError ? (
        <div className="border-destructive/40 bg-destructive/5 text-destructive rounded-lg border p-4 text-sm">
          {(error as Error)?.message ?? __('Failed to load analytics.', 'flexa-extra')}
        </div>
      ) : isLoading ? (
        <div className="mt-20 flex justify-center">
          <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard
              label={__('Orders with options', 'flexa-extra')}
              value={String(data?.totals.orders ?? 0)}
            />
            <StatCard
              label={__('Options selected', 'flexa-extra')}
              value={String(data?.totals.selections ?? 0)}
            />
            <StatCard
              label={__('Extra revenue', 'flexa-extra')}
              value={money(data?.totals.revenue ?? 0)}
            />
          </div>

          {data?.scanned.capped && (
            <p className="text-muted-foreground text-xs">
              {sprintf(
                /* translators: %d: maximum number of orders scanned. */
                __('Showing the most recent %d orders in range; older orders were not scanned.', 'flexa-extra'),
                data.scanned.max,
              )}
            </p>
          )}

          {hasData ? (
            <div className="border-border overflow-hidden rounded-lg border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                  <tr>
                    <th className="px-4 py-2.5 font-medium">{__('Option', 'flexa-extra')}</th>
                    <th className="px-4 py-2.5 font-medium">{__('Field', 'flexa-extra')}</th>
                    <th className="px-4 py-2.5 text-right font-medium">{__('Times chosen', 'flexa-extra')}</th>
                    <th className="px-4 py-2.5 text-right font-medium">{__('Revenue', 'flexa-extra')}</th>
                  </tr>
                </thead>
                <tbody>
                  {options.map((opt) => (
                    <OptionRow key={`${opt.field}|${opt.option}`} opt={opt} money={money} />
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="border-border text-muted-foreground rounded-lg border border-dashed py-16 text-center text-sm">
              {__('No option selections in this period yet.', 'flexa-extra')}
            </div>
          )}
        </>
      )}
    </div>
  );
}

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="border-border bg-card rounded-lg border p-4">
      <p className="text-muted-foreground text-xs">{label}</p>
      <p className="mt-1 text-2xl font-semibold">{value}</p>
    </div>
  );
}

function OptionRow({ opt, money }: { opt: AnalyticsOption; money: (n: number) => string }) {
  const isAction = opt.type === 'action';
  return (
    <tr className="border-border border-t">
      <td className="px-4 py-2.5">
        <span className="font-medium">{opt.name || __('(no label)', 'flexa-extra')}</span>
        {isAction && (
          <span className="bg-muted text-muted-foreground ml-2 rounded px-1.5 py-0.5 text-[10px] uppercase">
            {__('Fee', 'flexa-extra')}
          </span>
        )}
      </td>
      <td className="text-muted-foreground px-4 py-2.5">{opt.field_label}</td>
      <td className="px-4 py-2.5 text-right tabular-nums">{opt.count}</td>
      <td className="px-4 py-2.5 text-right tabular-nums">{money(opt.revenue)}</td>
    </tr>
  );
}
