import { __ } from '@wordpress/i18n';
import {
  Copy,
  Download,
  LayoutGrid,
  LayoutTemplate,
  Loader2,
  Pencil,
  Plus,
  Trash2,
  Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { OptionSetSummary } from '@/lib/schema/option-set';
import {
  useCreateOptionSetMutation,
  useDeleteOptionSetMutation,
  useDuplicateOptionSetMutation,
  useImportOptionSetsMutation,
  useOptionSetsQuery,
} from '@/lib/queries/option-sets';
import { downloadEnvelope, readJsonFile } from '@/lib/export-import';
import { PresetDefinition } from '@/lib/fields/presets';
import {
  useOnboardingQuery,
  useUpdateOnboardingMutation,
} from '@/lib/queries/onboarding';
import { PresetPicker } from './PresetPicker';
import { WelcomeOverlay } from '@/pages/onboarding/WelcomeOverlay';
import { QuickStartBanner } from '@/pages/onboarding/QuickStartBanner';
import { showToast } from '@/components/custom/showToast';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export default function OptionSets() {
  const navigate = useNavigate();
  const { data: items = [], isLoading } = useOptionSetsQuery();
  const duplicateMutation = useDuplicateOptionSetMutation();
  const deleteMutation = useDeleteOptionSetMutation();
  const importMutation = useImportOptionSetsMutation();
  const createMutation = useCreateOptionSetMutation();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [pendingPresetId, setPendingPresetId] = useState<string | null>(null);

  const { data: onboarding } = useOnboardingQuery();
  const onboardingMutation = useUpdateOnboardingMutation();
  const status = onboarding?.status ?? 'completed';
  const showWelcome = status === 'pending' && !pickerOpen;
  const showQuickStart = status === 'in_progress';

  const handleBrowseTemplates = () => {
    if (status === 'pending') onboardingMutation.mutate('in_progress');
    setPickerOpen(true);
  };

  const handleStartBlank = () => {
    if (status === 'pending') onboardingMutation.mutate('in_progress');
    navigate('/option-sets/new');
  };

  const handleSkipOnboarding = () => onboardingMutation.mutate('dismissed');
  const handleFinishOnboarding = () => onboardingMutation.mutate('completed');

  const handleSelectPreset = async (preset: PresetDefinition) => {
    setPendingPresetId(preset.id);
    try {
      const created = await createMutation.mutateAsync(preset.build());
      setPickerOpen(false);
      navigate(`/option-sets/${created.id}`);
    } catch {
      // The mutation surfaces its own error toast.
    } finally {
      setPendingPresetId(null);
    }
  };

  const handleDuplicate = (set: OptionSetSummary) => {
    duplicateMutation.mutate(set.id);
  };

  const handleDelete = (set: OptionSetSummary) => {
    // eslint-disable-next-line no-alert
    if (window.confirm(__('Delete this option set? This cannot be undone.', 'flexa-extra'))) {
      deleteMutation.mutate(set.id);
    }
  };

  const handleImportFile = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = ''; // allow re-selecting the same file
    if (!file) return;
    try {
      const payload = await readJsonFile(file);
      importMutation.mutate(payload);
    } catch {
      showToast.error(__('That file is not valid JSON.', 'flexa-extra'));
    }
  };

  return (
    <div className="mx-auto mt-8 max-w-7xl space-y-6 px-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">
            {__('Option Sets', 'flexa-extra')}
          </h1>
          <p className="text-muted-foreground mt-1">
            {__('Create groups of extra options and assign them to your products.', 'flexa-extra')}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <input
            ref={fileInputRef}
            type="file"
            accept="application/json,.json"
            className="hidden"
            onChange={handleImportFile}
          />
          <Button
            variant="outline"
            size="lg"
            onClick={() => fileInputRef.current?.click()}
            disabled={importMutation.isPending}
          >
            {importMutation.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Upload className="h-4 w-4" />
            )}
            {__('Import', 'flexa-extra')}
          </Button>
          {items.length > 0 && (
            <Button variant="outline" size="lg" onClick={() => downloadEnvelope(items)}>
              <Download className="h-4 w-4" />
              {__('Export all', 'flexa-extra')}
            </Button>
          )}
          <Button variant="outline" size="lg" onClick={handleBrowseTemplates}>
            <LayoutTemplate className="h-4 w-4" />
            {__('Start from a template', 'flexa-extra')}
          </Button>
          <Button size="lg" onClick={handleStartBlank}>
            <Plus className="h-4 w-4" />
            {__('New Option Set', 'flexa-extra')}
          </Button>
        </div>
      </div>

      <WelcomeOverlay
        open={showWelcome}
        onBrowseTemplates={handleBrowseTemplates}
        onStartBlank={handleStartBlank}
        onSkip={handleSkipOnboarding}
      />

      {showQuickStart && (
        <QuickStartBanner
          hasSets={items.length > 0}
          onBrowseTemplates={handleBrowseTemplates}
          onCreateAnother={handleStartBlank}
          onOpenSettings={() => navigate('/settings')}
          onFinish={handleFinishOnboarding}
        />
      )}

      <PresetPicker
        open={pickerOpen}
        pendingId={pendingPresetId}
        onClose={() => setPickerOpen(false)}
        onSelectPreset={handleSelectPreset}
      />

      {isLoading ? (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
        </div>
      ) : items.length === 0 ? (
        <EmptyState
          onCreate={handleStartBlank}
          onBrowseTemplates={handleBrowseTemplates}
        />
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
                        onClick={() => downloadEnvelope([set])}
                      >
                        <Download className="h-4 w-4" />
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

function EmptyState({
  onCreate,
  onBrowseTemplates,
}: {
  onCreate: () => void;
  onBrowseTemplates: () => void;
}) {
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
      <div className="mt-5 flex items-center gap-2">
        <Button variant="outline" onClick={onBrowseTemplates}>
          <LayoutTemplate className="h-4 w-4" />
          {__('Start from a template', 'flexa-extra')}
        </Button>
        <Button onClick={onCreate}>
          <Plus className="h-4 w-4" />
          {__('Create your first option set', 'flexa-extra')}
        </Button>
      </div>
    </div>
  );
}
