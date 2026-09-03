import { __ } from '@wordpress/i18n';
import { CheckCircle2, Rocket, X } from 'lucide-react';

import { Button } from '@/components/ui/button';

interface QuickStartBannerProps {
  /** True once the store has at least one option set. */
  hasSets: boolean;
  onBrowseTemplates: () => void;
  onCreateAnother: () => void;
  onOpenSettings: () => void;
  onFinish: () => void;
}

/**
 * The quick-start state after the welcome step. It never blocks the list: it
 * sits above it as a dismissible card. Before the first set exists it nudges the
 * user to finish; once a set exists it confirms success and points at the next
 * useful actions.
 */
export function QuickStartBanner({
  hasSets,
  onBrowseTemplates,
  onCreateAnother,
  onOpenSettings,
  onFinish,
}: QuickStartBannerProps) {
  return (
    <div className="border-primary/30 bg-primary/5 relative overflow-hidden rounded-xl border p-5">
      <button
        type="button"
        onClick={onFinish}
        aria-label={__('Dismiss the quick-start guide', 'flexa-extra')}
        className="text-muted-foreground hover:text-foreground absolute top-3 right-3"
      >
        <X className="h-4 w-4" />
      </button>

      <div className="flex items-start gap-4">
        <div className="bg-primary/10 text-primary flex h-10 w-10 flex-none items-center justify-center rounded-full">
          {hasSets ? <CheckCircle2 className="h-5 w-5" /> : <Rocket className="h-5 w-5" />}
        </div>
        <div className="min-w-0">
          <h2 className="font-semibold">
            {hasSets
              ? __('Your first option set is ready', 'flexa-extra')
              : __('Finish your first option set', 'flexa-extra')}
          </h2>
          <p className="text-muted-foreground mt-1 text-sm">
            {hasSets
              ? __(
                  'Open a product it applies to and check the extra fields on the page. From here you can add more sets or fine-tune how they look.',
                  'flexa-extra',
                )
              : __(
                  'Pick a template to drop in ready-made fields, then edit and publish it. You can leave and come back any time.',
                  'flexa-extra',
                )}
          </p>

          <div className="mt-4 flex flex-wrap items-center gap-2">
            {hasSets ? (
              <>
                <Button size="sm" onClick={onCreateAnother}>
                  {__('Create another', 'flexa-extra')}
                </Button>
                <Button variant="outline" size="sm" onClick={onOpenSettings}>
                  {__('Display settings', 'flexa-extra')}
                </Button>
                <Button variant="ghost" size="sm" onClick={onFinish}>
                  {__('Got it', 'flexa-extra')}
                </Button>
              </>
            ) : (
              <>
                <Button size="sm" onClick={onBrowseTemplates}>
                  {__('Browse templates', 'flexa-extra')}
                </Button>
                <Button variant="ghost" size="sm" onClick={onFinish}>
                  {__('Dismiss', 'flexa-extra')}
                </Button>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
