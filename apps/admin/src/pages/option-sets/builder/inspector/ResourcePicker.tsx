import { useEffect, useMemo, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useQuery } from '@tanstack/react-query';
import { Loader2, X } from 'lucide-react';

import { ResourceItem, ResourceType, resolveResources, searchResources } from '@/lib/api/resources';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface Props {
  type: ResourceType;
  value: number[];
  onChange: (ids: number[]) => void;
  placeholder?: string;
}

export function ResourcePicker({ type, value, onChange, placeholder }: Props) {
  const [query, setQuery] = useState('');
  const [debounced, setDebounced] = useState('');
  const [open, setOpen] = useState(false);
  const boxRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const t = window.setTimeout(() => setDebounced(query), 250);
    return () => window.clearTimeout(t);
  }, [query]);

  useEffect(() => {
    const onDocClick = (e: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, []);

  const idsKey = value.join(',');
  const resolved = useQuery({
    queryKey: ['resolve', type, idsKey],
    queryFn: () => resolveResources(type, value),
    enabled: value.length > 0,
  });

  const search = useQuery({
    queryKey: ['search', type, debounced],
    queryFn: () => searchResources(type, debounced),
    enabled: open,
  });

  const labelMap = useMemo(() => {
    const map = new Map<number, string>();
    (resolved.data ?? []).forEach((r) => map.set(r.id, r.label));
    (search.data ?? []).forEach((r) => map.set(r.id, r.label));
    return map;
  }, [resolved.data, search.data]);

  const add = (item: ResourceItem) => {
    if (!value.includes(item.id)) onChange([...value, item.id]);
    setQuery('');
  };
  const remove = (id: number) => onChange(value.filter((v) => v !== id));

  const results = (search.data ?? []).filter((r) => !value.includes(r.id));

  return (
    <div ref={boxRef} className="relative">
      {value.length > 0 && (
        <div className="mb-2 flex flex-wrap gap-1.5">
          {value.map((id) => (
            <span
              key={id}
              className="bg-muted inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs"
            >
              {labelMap.get(id) ?? `#${id}`}
              <button
                type="button"
                onClick={() => remove(id)}
                className="text-muted-foreground hover:text-destructive"
              >
                <X className="h-3 w-3" />
              </button>
            </span>
          ))}
        </div>
      )}

      <Input
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        onFocus={() => setOpen(true)}
        placeholder={placeholder ?? __('Search…', 'flexa-extra')}
      />

      {open && (debounced.length > 0 || search.isFetching) && (
        <div className="border-border bg-popover absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md border shadow-lg">
          {search.isFetching ? (
            <div className="text-muted-foreground flex items-center gap-2 px-3 py-2 text-sm">
              <Loader2 className="h-4 w-4 animate-spin" />
              {__('Searching…', 'flexa-extra')}
            </div>
          ) : results.length === 0 ? (
            <div className="text-muted-foreground px-3 py-2 text-sm">
              {__('No matches', 'flexa-extra')}
            </div>
          ) : (
            results.map((item) => (
              <button
                key={item.id}
                type="button"
                onClick={() => add(item)}
                className={cn(
                  'hover:bg-muted flex w-full items-center px-3 py-2 text-left text-sm',
                )}
              >
                {item.label}
              </button>
            ))
          )}
        </div>
      )}
    </div>
  );
}
