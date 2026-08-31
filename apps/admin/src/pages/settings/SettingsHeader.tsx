import { __ } from '@wordpress/i18n';
import { motion } from 'framer-motion';
import { RotateCcw, Save } from 'lucide-react';

import { Button } from '@/components/ui/button';

interface SettingsHeaderProps {
  saving: boolean;
}

export default function SettingsHeader({ saving }: SettingsHeaderProps) {
  return (
    <motion.div initial={{ opacity: 0, y: -20 }} animate={{ opacity: 1, y: 0 }} className="mb-8">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{__('Settings', 'flexa-extra')}</h1>
          <p className="text-muted-foreground mt-1">
            {__('Configure how extra product options behave on your store.', 'flexa-extra')}
          </p>
        </div>
        <Button type="submit" form="flexa-extra-settings-form" disabled={saving} size="lg">
          {saving ? (
            <>
              <motion.div
                animate={{ rotate: 360 }}
                transition={{ duration: 1, repeat: Infinity, ease: 'linear' }}
              >
                <RotateCcw className="h-4 w-4" />
              </motion.div>
              {__('Saving...', 'flexa-extra')}
            </>
          ) : (
            <>
              <Save className="h-4 w-4" />
              {__('Save Settings', 'flexa-extra')}
            </>
          )}
        </Button>
      </div>
    </motion.div>
  );
}
