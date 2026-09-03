import { useEffect, useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { AnimatePresence } from 'framer-motion';
import { FormProvider, useForm } from 'react-hook-form';

import { DEFAULT_SETTINGS } from '@/lib/helpers/settings.helper';
import { useSaveSettingsMutation, useSettingsQuery } from '@/lib/queries/settings';
import { SettingsFormData, settingsFormSchema } from '@/lib/schema/settings';

import SettingsHeader from './SettingsHeader';
import SettingsNavigation from './SettingsNavigation';
import AdvancedTab from './tabs/AdvancedTab';
import DisplayTab from './tabs/DisplayTab';
import GeneralTab from './tabs/GeneralTab';
import StyleTab from './tabs/StyleTab';

export default function Settings() {
  const { data: settings } = useSettingsQuery();
  const saveSettingsMutation = useSaveSettingsMutation();

  const form = useForm<SettingsFormData>({
    resolver: zodResolver(settingsFormSchema),
    defaultValues: settings ?? DEFAULT_SETTINGS,
  });

  const [activeTab, setActiveTab] = useState('general');

  useEffect(() => {
    form.reset(settings ?? DEFAULT_SETTINGS);
  }, [settings, form]);

  const onSubmit = (data: SettingsFormData) => saveSettingsMutation.mutate(data);

  return (
    <FormProvider {...form}>
      <form id="flexa-extra-settings-form" onSubmit={form.handleSubmit(onSubmit)}>
        <div className="mx-auto mt-8 min-h-screen max-w-7xl space-y-6 px-6 pb-16">
          <SettingsHeader saving={saveSettingsMutation.isPending} />

          <div className="flex flex-col gap-8 lg:flex-row">
            <SettingsNavigation activeTab={activeTab} setActiveTab={setActiveTab} />

            <div className="min-w-0 flex-1">
              <AnimatePresence mode="wait">
                {activeTab === 'general' && <GeneralTab key="general" />}
                {activeTab === 'display' && <DisplayTab key="display" />}
                {activeTab === 'style' && <StyleTab key="style" />}
                {activeTab === 'advanced' && <AdvancedTab key="advanced" />}
              </AnimatePresence>
            </div>
          </div>
        </div>
      </form>
    </FormProvider>
  );
}
