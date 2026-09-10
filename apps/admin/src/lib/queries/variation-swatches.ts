import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  AttributeSwatch,
  SwatchType,
  TermSwatch,
  TermUpdate,
  fetchAttributes,
  saveAttributeType,
  saveTerm,
} from '@/lib/api/variation-swatches';
import { showToast } from '@/components/custom/showToast';

const KEY = ['variation-swatches'] as const;

export function useVariationAttributesQuery() {
  return useQuery({ queryKey: KEY, queryFn: fetchAttributes });
}

export function useSaveAttributeTypeMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ taxonomy, type }: { taxonomy: string; type: SwatchType }) =>
      saveAttributeType(taxonomy, type),
    onSuccess: (type, { taxonomy }) => {
      queryClient.setQueryData<AttributeSwatch[]>(KEY, (prev) =>
        (prev ?? []).map((a) => (a.taxonomy === taxonomy ? { ...a, type } : a)),
      );
    },
    onError: (error: Error) => showToast.error(error.message),
  });
}

export function useSaveTermMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (update: TermUpdate) => saveTerm(update),
    onSuccess: (term: TermSwatch) => {
      queryClient.setQueryData<AttributeSwatch[]>(KEY, (prev) =>
        (prev ?? []).map((a) => ({
          ...a,
          terms: a.terms.map((t) =>
            t.id === term.id ? { ...t, color: term.color, image: term.image } : t,
          ),
        })),
      );
    },
    onError: (error: Error) => showToast.error(error.message),
  });
}
