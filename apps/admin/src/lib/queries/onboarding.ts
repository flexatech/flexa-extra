import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  fetchOnboarding,
  initialOnboarding,
  OnboardingState,
  OnboardingStatus,
  postOnboarding,
} from '@/lib/api/onboarding';

const KEY = ['onboarding'] as const;

export function useOnboardingQuery() {
  return useQuery({
    queryKey: KEY,
    queryFn: fetchOnboarding,
    initialData: initialOnboarding,
  });
}

export function useUpdateOnboardingMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (status: OnboardingStatus) => postOnboarding(status),
    // Optimistic: the guide should react instantly, and the status is a tiny
    // enum with no failure the user needs to recover from.
    onMutate: async (status) => {
      await queryClient.cancelQueries({ queryKey: KEY });
      const previous = queryClient.getQueryData<OnboardingState>(KEY);
      if (previous) {
        queryClient.setQueryData<OnboardingState>(KEY, { ...previous, status });
      }
      return { previous };
    },
    onError: (_error, _status, context) => {
      if (context?.previous) {
        queryClient.setQueryData(KEY, context.previous);
      }
    },
    onSuccess: (data) => {
      if (data) queryClient.setQueryData(KEY, data);
    },
  });
}
