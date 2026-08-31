import { __ } from '@wordpress/i18n';
import {
  DndContext,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, LayoutGrid, Trash2 } from 'lucide-react';
import { useFieldArray, useFormContext, useWatch } from 'react-hook-form';

import { Field, OptionSet } from '@/lib/schema/option-set';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface Props {
  selectedId: string | null;
  onSelect: (id: string | null) => void;
}

export function FieldCanvas({ selectedId, onSelect }: Props) {
  const { control } = useFormContext<OptionSet>();
  const { fields, move, remove } = useFieldArray({ control, name: 'fields', keyName: '_rhfId' });
  const watched = (useWatch({ control, name: 'fields' }) ?? []) as Field[];

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

  const onDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const from = fields.findIndex((f) => f.id === active.id);
    const to = fields.findIndex((f) => f.id === over.id);
    if (from !== -1 && to !== -1) move(from, to);
  };

  if (fields.length === 0) {
    return (
      <div className="border-border text-muted-foreground flex min-h-[320px] flex-col items-center justify-center rounded-xl border border-dashed text-center">
        <LayoutGrid className="mb-3 h-8 w-8" />
        <p className="text-sm font-medium">{__('No fields yet', 'flexa-extra')}</p>
        <p className="mt-1 max-w-xs text-xs">
          {__('Pick a field type from the left to start building this option set.', 'flexa-extra')}
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-2">
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
        <SortableContext items={fields.map((f) => f.id)} strategy={verticalListSortingStrategy}>
          {fields.map((field, index) => (
            <SortableFieldCard
              key={field._rhfId}
              id={field.id}
              data={watched[index]}
              selected={selectedId === field.id}
              onSelect={() => onSelect(field.id)}
              onRemove={() => {
                remove(index);
                if (selectedId === field.id) onSelect(null);
              }}
            />
          ))}
        </SortableContext>
      </DndContext>
    </div>
  );
}

function SortableFieldCard({
  id,
  data,
  selected,
  onSelect,
  onRemove,
}: {
  id: string;
  data?: Field;
  selected: boolean;
  onSelect: () => void;
  onRemove: () => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });

  const style = { transform: CSS.Transform.toString(transform), transition };
  const label = data?.label || __('Untitled field', 'flexa-extra');

  return (
    <div
      ref={setNodeRef}
      style={style}
      onClick={onSelect}
      className={cn(
        'bg-card flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 transition-colors',
        selected ? 'border-primary ring-primary/20 ring-2' : 'border-border hover:border-primary/50',
        isDragging && 'opacity-60 shadow-lg',
      )}
    >
      <button
        type="button"
        className="text-muted-foreground hover:text-foreground cursor-grab touch-none active:cursor-grabbing"
        {...attributes}
        {...listeners}
        onClick={(e) => e.stopPropagation()}
      >
        <GripVertical className="h-4 w-4" />
      </button>
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium">
          {label}
          {data?.required ? <span className="text-destructive ml-1">*</span> : null}
        </p>
        <p className="text-muted-foreground text-xs capitalize">{data?.type}</p>
      </div>
      <Button
        type="button"
        variant="ghost"
        size="icon"
        className="hover:text-destructive h-7 w-7"
        onClick={(e) => {
          e.stopPropagation();
          onRemove();
        }}
      >
        <Trash2 className="h-4 w-4" />
      </Button>
    </div>
  );
}
