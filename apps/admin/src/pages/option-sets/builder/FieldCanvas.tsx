import { __ } from '@wordpress/i18n';
import { useDroppable } from '@dnd-kit/core';
import {
  SortableContext,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { AnimatePresence, motion } from 'framer-motion';
import { GripVertical, LayoutGrid, Trash2 } from 'lucide-react';
import { useFormContext, useWatch } from 'react-hook-form';

import { Field, OptionSet } from '@/lib/schema/option-set';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type DisplayItem =
  | { kind: 'field'; field: Field; index: number }
  | { kind: 'placeholder'; label: string };

interface Props {
  selectedId: string | null;
  onSelect: (id: string | null) => void;
  move: (from: number, to: number) => void;
  remove: (index?: number | number[]) => void;
  placeholderIndex?: number | null;
  placeholderLabel?: string | null;
}

export function FieldCanvas({
  selectedId,
  onSelect,
  move,
  remove,
  placeholderIndex,
  placeholderLabel,
}: Props) {
  const { control } = useFormContext<OptionSet>();
  const watched = (useWatch({ control, name: 'fields' }) ?? []) as Field[];
  const { setNodeRef, isOver } = useDroppable({ id: 'canvas' });

  const showPlaceholder = placeholderIndex !== null && placeholderIndex !== undefined && !!placeholderLabel;

  if (watched.length === 0) {
    return (
      <div
        ref={setNodeRef}
        className={cn(
          'border-border text-muted-foreground flex min-h-[320px] flex-col items-center justify-center rounded-xl border border-dashed text-center transition-colors',
          isOver && 'border-primary bg-primary/5 text-primary',
        )}
      >
        <LayoutGrid className="mb-3 h-8 w-8" />
        <p className="text-sm font-medium">{__('No fields yet', 'flexa-extra')}</p>
        <p className={cn('mt-1 max-w-xs text-xs', isOver && 'text-primary/70')}>
          {isOver
            ? __('Drop to add', 'flexa-extra')
            : __('Pick a field type from the left to start building this option set.', 'flexa-extra')}
        </p>
      </div>
    );
  }

  const displayItems: DisplayItem[] = [];
  watched.forEach((field, index) => {
    if (showPlaceholder && index === placeholderIndex) {
      displayItems.push({ kind: 'placeholder', label: placeholderLabel! });
    }
    displayItems.push({ kind: 'field', field, index });
  });
  if (showPlaceholder && placeholderIndex === watched.length) {
    displayItems.push({ kind: 'placeholder', label: placeholderLabel! });
  }

  return (
    <div ref={setNodeRef} className="space-y-2">
      <SortableContext items={watched.map((f) => f.id)} strategy={verticalListSortingStrategy}>
        <AnimatePresence initial={false}>
          {displayItems.map((item) => {
            if (item.kind === 'placeholder') {
              return (
                <motion.div
                  key="placeholder"
                  initial={{ opacity: 0, height: 0 }}
                  animate={{ opacity: 1, height: 'auto' }}
                  exit={{ opacity: 0, height: 0 }}
                  transition={{ duration: 0.14, ease: 'easeOut' }}
                  style={{ overflow: 'hidden' }}
                >
                  <PlaceholderCard label={item.label} />
                </motion.div>
              );
            }

            return (
              <motion.div
                key={item.field.id}
                initial={{ opacity: 0, height: 0, overflow: 'hidden' }}
                animate={{ opacity: 1, height: 'auto', transitionEnd: { overflow: 'visible' } }}
                exit={{ opacity: 0, height: 0, overflow: 'hidden' }}
                transition={{ duration: 0.2, ease: 'easeInOut' }}
              >
                <SortableFieldCard
                  id={item.field.id}
                  data={item.field}
                  selected={selectedId === item.field.id}
                  onSelect={() => onSelect(item.field.id)}
                  onRemove={() => {
                    remove(item.index);
                    if (selectedId === item.field.id) onSelect(null);
                  }}
                />
              </motion.div>
            );
          })}
        </AnimatePresence>
      </SortableContext>
    </div>
  );
}

function PlaceholderCard({ label }: { label: string }) {
  return (
    <div className="border-primary/50 bg-primary/5 flex items-center gap-2 rounded-lg border border-dashed px-3 py-2.5">
      <div className="h-4 w-4 shrink-0" />
      <p className="text-primary/60 min-w-0 flex-1 truncate text-sm font-medium">{label}</p>
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
