import { __ } from '@wordpress/i18n';
import { BarChart3 } from 'lucide-react';

/**
 * Placeholder for variation-swatch analytics. Tracking is not wired up yet;
 * this reserves the screen so it can be filled in later.
 */
export default function VariationSwatchesAnalytics() {
  return (
    <div className="mx-auto mt-8 max-w-5xl px-6 pb-16">
      <div>
        <h1 className="flex items-center gap-2 text-xl font-semibold">
          <BarChart3 className="h-5 w-5" />
          {__('Analytics', 'flexa-extra')}
        </h1>
        <p className="text-muted-foreground mt-1 text-sm">
          {__('See which swatches shoppers pick most.', 'flexa-extra')}
        </p>
      </div>

      <div className="border-border mt-6 flex flex-col items-center justify-center rounded-lg border border-dashed py-20 text-center">
        <BarChart3 className="text-muted-foreground/50 h-10 w-10" />
        <p className="text-foreground mt-4 text-sm font-medium">
          {__('No data yet', 'flexa-extra')}
        </p>
        <p className="text-muted-foreground mt-1 max-w-sm text-sm">
          {__('Swatch analytics is coming soon. Once tracking is enabled, the most-picked colors, images and buttons will show up here.', 'flexa-extra')}
        </p>
      </div>
    </div>
  );
}
