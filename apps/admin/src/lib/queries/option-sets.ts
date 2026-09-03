import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
  createOptionSet,
  deleteOptionSet,
  duplicateOptionSet,
  fetchOptionSet,
  fetchOptionSets,
  importOptionSets,
  OptionSetInput,
  updateOptionSet,
} from '@/lib/api/option-sets';
import { showToast } from '@/components/custom/showToast';

const LIST_KEY = ['option-sets'] as const;
const itemKey = (id: number) => ['option-set', id] as const;

export function useOptionSetsQuery() {
  return useQuery({ queryKey: LIST_KEY, queryFn: fetchOptionSets });
}

export function useOptionSetQuery(id: number | null) {
  return useQuery({
    queryKey: itemKey(id ?? 0),
    queryFn: () => fetchOptionSet(id as number),
    enabled: !!id,
  });
}

export function useCreateOptionSetMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: OptionSetInput) => createOptionSet(input),
    onSuccess: () => {
      showToast.success(__('Option set created!', 'flexa-extra'));
      queryClient.invalidateQueries({ queryKey: LIST_KEY });
    },
    onError: (error: Error) => showToast.error(error.message),
  });
}

export function useUpdateOptionSetMutation(id: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: OptionSetInput) => updateOptionSet(id, input),
    onSuccess: () => {
      showToast.success(__('Option set saved!', 'flexa-extra'));
      queryClient.invalidateQueries({ queryKey: LIST_KEY });
      queryClient.invalidateQueries({ queryKey: itemKey(id) });
    },
    onError: (error: Error) => showToast.error(error.message),
  });
}

export function useDeleteOptionSetMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteOptionSet(id),
    onSuccess: () => {
      showToast.success(__('Option set deleted', 'flexa-extra'));
      queryClient.invalidateQueries({ queryKey: LIST_KEY });
    },
    onError: (error: Error) => showToast.error(error.message),
  });
}

export function useDuplicateOptionSetMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => duplicateOptionSet(id),
    onSuccess: () => {
      showToast.success(__('Option set duplicated!', 'flexa-extra'));
      queryClient.invalidateQueries({ queryKey: LIST_KEY });
    },
    onError: (error: Error) => showToast.error(error.message),
  });
}

export function useImportOptionSetsMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: unknown) => importOptionSets(payload),
    onSuccess: (result) => {
      showToast.success(
        sprintf(
          /* translators: %d: number of option sets imported. */
          _n('Imported %d option set', 'Imported %d option sets', result.count, 'flexa-extra'),
          result.count,
        ),
      );
      queryClient.invalidateQueries({ queryKey: LIST_KEY });
    },
    onError: (error: Error) => showToast.error(error.message),
  });
}
