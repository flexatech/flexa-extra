import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { __ } from '@wordpress/i18n';

import { fetchSettings, postSettings } from '@/lib/api/settings';
import { SettingsFormData } from '@/lib/schema/settings';
import { showToast } from '@/components/custom/showToast';

export function useSettingsQuery() {
  return useQuery({
    queryKey: ['settings'],
    queryFn: fetchSettings,
  });
}

export function useSaveSettingsMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['settings'],
    mutationFn: async (data: SettingsFormData) => postSettings(data),
    onSuccess: () => {
      showToast.success(__('Settings saved!', 'flexa-extra'));
      queryClient.invalidateQueries({ queryKey: ['settings'] });
    },
    onError: () => {
      showToast.error(__('Oops! Something went wrong!', 'flexa-extra'));
    },
  });
}
