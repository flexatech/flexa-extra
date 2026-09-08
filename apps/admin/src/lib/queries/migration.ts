import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { fetchMigrationSources, importFromSource } from '@/lib/api/migration';
import { showToast } from '@/components/custom/showToast';

const SOURCES_KEY = ['migration-sources'] as const;

export function useMigrationSourcesQuery() {
  return useQuery({ queryKey: SOURCES_KEY, queryFn: fetchMigrationSources });
}

export function useImportFromSourceMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (slug: string) => importFromSource(slug),
    onSuccess: (result) => {
      if (result.message) {
        showToast.success(result.message);
      }
      // New drafts landed; refresh the option-set list and the source counts.
      queryClient.invalidateQueries({ queryKey: ['option-sets'] });
      queryClient.invalidateQueries({ queryKey: SOURCES_KEY });
    },
    onError: (error: Error) => showToast.error(error.message),
  });
}
