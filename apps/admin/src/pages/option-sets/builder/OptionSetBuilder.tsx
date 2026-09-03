import { useEffect, useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { zodResolver } from '@hookform/resolvers/zod';
import { FormProvider, useFieldArray, useForm } from 'react-hook-form';
import { GripVertical, Loader2 } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragOverEvent,
  type DragStartEvent,
} from '@dnd-kit/core';

import { FieldType, OptionSet, optionSetSchema } from '@/lib/schema/option-set';
import { createField, emptyTargeting, getFieldCatalog } from '@/lib/fields/registry';
import { ActionsPanel } from './ActionsPanel';
import {
  useCreateOptionSetMutation,
  useOptionSetQuery,
  useUpdateOptionSetMutation,
} from '@/lib/queries/option-sets';
import { BuilderHeader } from './BuilderHeader';
import { FieldPalette } from './FieldPalette';
import { FieldCanvas } from './FieldCanvas';
import { Inspector } from './Inspector';
import { AssignmentPanel } from './AssignmentPanel';

type BuilderView = 'fields' | 'assignment' | 'actions';

function blankOptionSet(): OptionSet {
  return {
    name: __('Untitled Option Set', 'flexa-extra'),
    status: false,
    fields: [],
    targeting: emptyTargeting(),
    actions: [],
  };
}

export default function OptionSetBuilder() {
  const params = useParams();
  const navigate = useNavigate();
  const id = params.id ? Number(params.id) : null;
  const isNew = !id;

  const { data, isLoading } = useOptionSetQuery(id);
  const createMutation = useCreateOptionSetMutation();
  const updateMutation = useUpdateOptionSetMutation(id ?? 0);

  const [view, setView] = useState<BuilderView>('fields');
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [activePaletteType, setActivePaletteType] = useState<FieldType | null>(null);
  const [placeholderIndex, setPlaceholderIndex] = useState<number | null>(null);

  const form = useForm<OptionSet>({
    resolver: zodResolver(optionSetSchema),
    defaultValues: blankOptionSet(),
  });

  const fieldArray = useFieldArray({ control: form.control, name: 'fields', keyName: '_rhfId' });

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

  useEffect(() => {
    if (data) {
      form.reset({
        id: data.id,
        name: data.name,
        status: data.status,
        fields: data.fields ?? [],
        targeting: data.targeting ?? emptyTargeting(),
        actions: data.actions ?? [],
      });
    }
  }, [data, form]);

  const isSaving = createMutation.isPending || updateMutation.isPending;

  const onSubmit = form.handleSubmit(async (values) => {
    const input = {
      name: values.name,
      status: values.status,
      fields: values.fields,
      targeting: values.targeting,
      actions: values.actions,
    };
    if (isNew) {
      const created = await createMutation.mutateAsync(input);
      navigate(`/option-sets/${created.id}`, { replace: true });
    } else {
      await updateMutation.mutateAsync(input);
    }
  });

  const heading = useMemo(
    () => (isNew ? __('New Option Set', 'flexa-extra') : __('Edit Option Set', 'flexa-extra')),
    [isNew],
  );

  const handleDragStart = (event: DragStartEvent) => {
    if (event.active.data.current?.source === 'palette') {
      setActivePaletteType(event.active.data.current.type as FieldType);
    }
  };

  const handleDragOver = (event: DragOverEvent) => {
    if (event.active.data.current?.source !== 'palette' || !event.over) {
      setPlaceholderIndex(null);
      return;
    }
    const currentFields = form.getValues('fields');
    const overIndex = currentFields.findIndex((f) => f.id === String(event.over!.id));
    setPlaceholderIndex(overIndex !== -1 ? overIndex : currentFields.length);
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    setActivePaletteType(null);
    setPlaceholderIndex(null);

    if (active.data.current?.source === 'palette') {
      if (!over) return;
      const type = active.data.current.type as FieldType;
      const field = createField(type);
      const currentFields = form.getValues('fields');
      const overIndex = currentFields.findIndex((f) => f.id === String(over.id));
      if (overIndex !== -1) {
        fieldArray.insert(overIndex, field);
      } else {
        fieldArray.append(field);
      }
      setSelectedId(field.id);
    } else {
      // Canvas → canvas reorder
      if (!over || active.id === over.id) return;
      const currentFields = form.getValues('fields');
      const from = currentFields.findIndex((f) => f.id === String(active.id));
      const to = currentFields.findIndex((f) => f.id === String(over.id));
      if (from !== -1 && to !== -1) fieldArray.move(from, to);
    }
  };

  const overlayLabel = useMemo(
    () =>
      activePaletteType
        ? (getFieldCatalog().find((e) => e.type === activePaletteType)?.label ?? activePaletteType)
        : null,
    [activePaletteType],
  );

  if (!isNew && isLoading) {
    return (
      <div className="mt-20 flex items-center justify-center">
        <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
      </div>
    );
  }

  return (
    <FormProvider {...form}>
      <form onSubmit={onSubmit} className="mx-auto mt-6 max-w-[1400px] px-6 pb-16">
        <BuilderHeader
          heading={heading}
          view={view}
          onViewChange={setView}
          isSaving={isSaving}
          onBack={() => navigate('/option-sets')}
        />

        {view === 'fields' && (
          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={handleDragStart}
            onDragOver={handleDragOver}
            onDragEnd={handleDragEnd}
          >
            <div className="mt-5 grid grid-cols-[220px_1fr_340px] gap-5">
              <FieldPalette
                onAddField={(type) => {
                  const field = createField(type);
                  fieldArray.append(field);
                  setSelectedId(field.id);
                }}
              />
              <FieldCanvas
                selectedId={selectedId}
                onSelect={setSelectedId}
                move={fieldArray.move}
                remove={fieldArray.remove}
                placeholderIndex={placeholderIndex}
                placeholderLabel={overlayLabel}
              />
              <Inspector selectedId={selectedId} onDeleted={() => setSelectedId(null)} />
            </div>

            <DragOverlay dropAnimation={null}>
              {activePaletteType && overlayLabel ? (
                <div className="bg-card border-primary ring-primary/20 flex w-[300px] cursor-grabbing items-center gap-2 rounded-lg border px-3 py-2.5 opacity-90 shadow-lg ring-2">
                  <GripVertical className="text-muted-foreground h-4 w-4 shrink-0" />
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{overlayLabel}</p>
                    <p className="text-muted-foreground text-xs capitalize">
                      {activePaletteType.replace('_', ' ')}
                    </p>
                  </div>
                </div>
              ) : null}
            </DragOverlay>
          </DndContext>
        )}

        {view === 'assignment' && (
          <div className="mt-5">
            <AssignmentPanel />
          </div>
        )}
        {view === 'actions' && (
          <div className="mt-5">
            <ActionsPanel />
          </div>
        )}
      </form>
    </FormProvider>
  );
}
