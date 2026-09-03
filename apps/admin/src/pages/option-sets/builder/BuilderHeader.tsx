import { __ } from '@wordpress/i18n';
import { ArrowLeft, Loader2, Save } from 'lucide-react';
import { Controller, useFormContext } from 'react-hook-form';

import { OptionSet } from '@/lib/schema/option-set';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type BuilderView = 'fields' | 'assignment' | 'actions';

interface Props {
  heading: string;
  view: BuilderView;
  onViewChange: (view: BuilderView) => void;
  isSaving: boolean;
  onBack: () => void;
}

export function BuilderHeader({ heading, view, onViewChange, isSaving, onBack }: Props) {
  const { register, control } = useFormContext<OptionSet>();

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-4">
        <div className="flex min-w-0 flex-1 items-center gap-3">
          <Button type="button" variant="ghost" size="icon" onClick={onBack}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div className="min-w-0 flex-1">
            <p className="text-muted-foreground text-xs">{heading}</p>
            <Input
              {...register('name')}
              className="h-9 w-full max-w-md border-transparent bg-transparent px-0 text-lg font-semibold shadow-none focus-visible:border-input focus-visible:px-3"
              placeholder={__('Option set name', 'flexa-extra')}
            />
          </div>
        </div>

        <div className="flex shrink-0 items-center gap-4">
          <Controller
            control={control}
            name="status"
            render={({ field }) => (
              <label className="flex cursor-pointer items-center gap-2 text-sm">
                <Switch checked={field.value} onCheckedChange={field.onChange} />
                <span className="text-muted-foreground">{__('Active', 'flexa-extra')}</span>
              </label>
            )}
          />
          <Button type="submit" disabled={isSaving}>
            {isSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {__('Save', 'flexa-extra')}
          </Button>
        </div>
      </div>

      <div className="border-border flex gap-1 border-b">
        <TabButton active={view === 'fields'} onClick={() => onViewChange('fields')}>
          {__('Fields', 'flexa-extra')}
        </TabButton>
        <TabButton active={view === 'actions'} onClick={() => onViewChange('actions')}>
          {__('Fees & discounts', 'flexa-extra')}
        </TabButton>
        <TabButton active={view === 'assignment'} onClick={() => onViewChange('assignment')}>
          {__('Product assignment', 'flexa-extra')}
        </TabButton>
      </div>
    </div>
  );
}

function TabButton({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        '-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors',
        active
          ? 'border-primary text-primary'
          : 'text-muted-foreground hover:text-foreground border-transparent',
      )}
    >
      {children}
    </button>
  );
}
