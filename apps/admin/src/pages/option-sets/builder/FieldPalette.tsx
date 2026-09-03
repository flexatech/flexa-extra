import { __ } from '@wordpress/i18n';
import {
  AlignLeft,
  CalendarDays,
  CheckSquare,
  ChevronDownSquare,
  CircleDot,
  Hash,
  Heading,
  MousePointerClick,
  Palette,
  Pipette,
  Plus,
  Type,
  type LucideIcon,
} from 'lucide-react';
import { useFieldArray, useFormContext } from 'react-hook-form';

import { OptionSet, FieldType } from '@/lib/schema/option-set';
import { createField, FieldCatalogEntry, getFieldCatalog } from '@/lib/fields/registry';
import { cn } from '@/lib/utils';

const ICONS: Record<string, LucideIcon> = {
  text: Type,
  textarea: AlignLeft,
  number: Hash,
  date_picker: CalendarDays,
  color_picker: Pipette,
  checkbox: CheckSquare,
  radio: CircleDot,
  dropdown: ChevronDownSquare,
  swatch: Palette,
  button: MousePointerClick,
  heading: Heading,
};

const GROUP_LABELS: Record<string, string> = {
  input: __('Input fields', 'flexa-extra'),
  choice: __('Choice fields', 'flexa-extra'),
  display: __('Layout', 'flexa-extra'),
};

interface Props {
  onSelectField: (id: string) => void;
}

export function FieldPalette({ onSelectField }: Props) {
  const { control } = useFormContext<OptionSet>();
  const { append } = useFieldArray({ control, name: 'fields' });

  const catalog = getFieldCatalog();
  const groups = ['input', 'choice', 'display'] as const;

  const addField = (type: FieldType) => {
    const field = createField(type);
    append(field);
    onSelectField(field.id);
  };

  return (
    <aside className="border-border bg-card h-fit rounded-xl border p-3">
      <p className="text-muted-foreground px-1 pb-2 text-xs font-semibold uppercase tracking-wide">
        {__('Add field', 'flexa-extra')}
      </p>
      <div className="space-y-4">
        {groups.map((group) => {
          const entries = catalog.filter((e) => e.group === group);
          if (entries.length === 0) return null;
          return (
            <div key={group}>
              <p className="text-muted-foreground mb-1.5 px-1 text-[11px] font-medium">
                {GROUP_LABELS[group]}
              </p>
              <div className="space-y-1">
                {entries.map((entry) => (
                  <PaletteButton key={entry.type} entry={entry} onAdd={() => addField(entry.type)} />
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </aside>
  );
}

function PaletteButton({ entry, onAdd }: { entry: FieldCatalogEntry; onAdd: () => void }) {
  const Icon = ICONS[entry.type] ?? Type;
  return (
    <button
      type="button"
      onClick={onAdd}
      className={cn(
        'group hover:border-primary hover:bg-muted/60 flex w-full items-center gap-2 rounded-md border border-transparent px-2 py-1.5 text-left text-sm transition-colors',
      )}
    >
      <Icon className="text-muted-foreground group-hover:text-primary h-4 w-4 shrink-0" />
      <span className="min-w-0 flex-1 truncate">{entry.label}</span>
      <Plus className="text-muted-foreground h-3.5 w-3.5 opacity-0 transition-opacity group-hover:opacity-100" />
    </button>
  );
}
