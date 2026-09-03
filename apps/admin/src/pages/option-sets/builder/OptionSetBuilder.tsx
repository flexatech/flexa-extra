import { useEffect, useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { zodResolver } from '@hookform/resolvers/zod';
import { FormProvider, useForm } from 'react-hook-form';
import { Loader2 } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';

import { OptionSet, optionSetSchema } from '@/lib/schema/option-set';
import { emptyTargeting } from '@/lib/fields/registry';
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

  const form = useForm<OptionSet>({
    resolver: zodResolver(optionSetSchema),
    defaultValues: blankOptionSet(),
  });

  // Hydrate the form when an existing set finishes loading.
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
          <div className="mt-5 grid grid-cols-[220px_1fr_340px] gap-5">
            <FieldPalette onSelectField={setSelectedId} />
            <FieldCanvas selectedId={selectedId} onSelect={setSelectedId} />
            <Inspector selectedId={selectedId} onDeleted={() => setSelectedId(null)} />
          </div>
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
