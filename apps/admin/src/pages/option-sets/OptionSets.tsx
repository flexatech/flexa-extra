import { __ } from '@wordpress/i18n';
import { Copy, LayoutGrid, Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

import { OptionSetSummary } from '@/lib/schema/option-set';
import {
  useCreateOptionSetMutation,
  useDeleteOptionSetMutation,
  useOptionSetsQuery,
} from '@/lib/queries/option-sets';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export default function OptionSets() {
  const navigate = useNavigate();
  const { data: items = [], isLoading } = useOptionSetsQuery();
  const createMutation = useCreateOptionSetMutation();
  const deleteMutation = useDeleteOptionSetMutation();

  const handleDuplicate = (set: OptionSetSummary) => {
    createMutation.mutate({
      name: `${set.name} (${__('copy', 'flexa-extra')})`,
      status: false,
      fields: set.fields,
      targeting: set.targeting,
    });
  };

  const handleDelete = (set: OptionSetSummary) => {
    // eslint-disable-next-line no-alert
    if (window.confirm(__('Delete this option set? This cannot be undone.', 'flexa-extra'))) {
      deleteMutation.mutate(set.id);
    }
  };

  return (
    <div className="mx-auto mt-[110px] max-w-7xl space-y-6 px-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">
            {__('Option Sets', 'flexa-extra')}
          </h1>
          <p className="text-muted-foreground mt-1">
            {__('Create groups of extra options and assign them to your products.', 'flexa-extra')}
          </p>
        </div>
        <Button size="lg" onClick={() => navigate('/option-sets/new')}>
          <Plus className="h-4 w-4" />
          {__('New Option Set', 'flexa-extra')}
        </Button>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
        </div>
      ) : items.length === 0 ? (
        <EmptyState onCreate={() => navigate('/option-sets/new')} />
      ) : (
        <div className="border-border bg-card overflow-hidden rounded-xl border">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-border text-muted-foreground border-b text-left">
                <th className="px-5 py-3 font-medium">{__('Name', 'flexa-extra')}</th>
                <th className="px-5 py-3 font-medium">{__('Fields', 'flexa-extra')}</th>
                <th className="px-5 py-3 font-medium">{__('Assignment', 'flexa-extra')}</th>
                <th className="px-5 py-3 font-medium">{__('Status', 'flexa-extra')}</th>
                <th className="px-5 py-3" />
              </tr>
            </thead>
            <tbody>
              {items.map((set) => (
                <tr
                  key={set.id}
                  className="border-border hover:bg-muted/50 cursor-pointer border-b transition-colors last:border-0"
                  onClick={() => navigate(`/option-sets/${set.id}`)}
                >
                  <td className="px-5 py-3 font-medium">{set.name}</td>
                  <td className="text-muted-foreground px-5 py-3">
                    {(set.fields?.length ?? 0).toString()}
                  </td>
                  <td className="text-muted-foreground px-5 py-3 capitalize">
                    {set.targeting?.mode ?? 'all'}
                  </td>
                  <td className="px-5 py-3">
                    <StatusPill active={set.status} />
                  </td>
                  <td className="px-5 py-3">
                    <div
                      className="flex items-center justify-end gap-1"
                      onClick={(e) => e.stopPropagation()}
                    >
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => navigate(`/option-sets/${set.id}`)}
                      >
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="icon" onClick={() => handleDuplicate(set)}>
                        <Copy className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="hover:text-destructive"
                        onClick={() => handleDelete(set)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function StatusPill({ active }: { active: boolean }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        active ? 'bg-success/15 text-success' : 'bg-muted text-muted-foreground',
      )}
    >
      {active ? __('Active', 'flexa-extra') : __('Draft', 'flexa-extra')}
    </span>
  );
}

function EmptyState({ onCreate }: { onCreate: () => void }) {
  return (
    <div className="border-border bg-card flex flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
      <div className="bg-muted text-primary mb-4 flex h-14 w-14 items-center justify-center rounded-full">
        <LayoutGrid className="h-6 w-6" />
      </div>
      <h3 className="text-lg font-semibold">{__('No option sets yet', 'flexa-extra')}</h3>
      <p className="text-muted-foreground mt-1 max-w-md text-sm">
        {__(
          'Option sets let you add extra fields like text, checkboxes, swatches and buttons to product pages.',
          'flexa-extra',
        )}
      </p>
      <Button className="mt-5" onClick={onCreate}>
        <Plus className="h-4 w-4" />
        {__('Create your first option set', 'flexa-extra')}
      </Button>
    </div>
  );
}
