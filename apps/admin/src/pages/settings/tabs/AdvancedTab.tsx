import { __ } from '@wordpress/i18n';
import { EyeOff, FileCode, RotateCcw, Zap } from 'lucide-react';
import { Controller, useFormContext } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';

import { SettingsFormData } from '@/lib/schema/settings';
import { useUpdateOnboardingMutation } from '@/lib/queries/onboarding';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { SettingRow } from '@/components/settings/SettingRow';
import { TabCard } from '@/components/settings/TabCard';

export default function AdvancedTab() {
  const { control } = useFormContext<SettingsFormData>();
  const navigate = useNavigate();
  const onboardingMutation = useUpdateOnboardingMutation();

  const handleReplayGuide = () => {
    onboardingMutation.mutate('pending');
    navigate('/option-sets');
  };

  return (
    <TabCard
      icon={Zap}
      title={__('Advanced', 'flexa-extra')}
      description={__('Fine-tune behavior and script loading.', 'flexa-extra')}
    >
      <Controller
        control={control}
        name="advanced.hideZeroSubtotal"
        render={({ field }) => (
          <SettingRow
            icon={EyeOff}
            label={__('Hide zero subtotal', 'flexa-extra')}
            description={__('Hide the extras subtotal when it equals zero.', 'flexa-extra')}
            control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
          />
        )}
      />
      <Controller
        control={control}
        name="advanced.loadScriptsAllPages"
        render={({ field }) => (
          <SettingRow
            icon={FileCode}
            label={__('Load scripts on all pages', 'flexa-extra')}
            description={__('Enable only if a builder needs the assets site-wide.', 'flexa-extra')}
            control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
          />
        )}
      />
      <SettingRow
        icon={RotateCcw}
        label={__('Replay setup guide', 'flexa-extra')}
        description={__('Show the first-run quick-start guide again on the Option Sets screen.', 'flexa-extra')}
        control={
          <Button variant="outline" size="sm" onClick={handleReplayGuide}>
            {__('Replay', 'flexa-extra')}
          </Button>
        }
      />
    </TabCard>
  );
}
