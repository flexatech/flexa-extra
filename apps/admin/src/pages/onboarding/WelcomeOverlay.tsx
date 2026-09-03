import { __ } from '@wordpress/i18n';
import { AnimatePresence, motion } from 'framer-motion';
import { LayoutTemplate, MousePointerClick, PencilRuler, Sparkles, Tag } from 'lucide-react';

import { Button } from '@/components/ui/button';

interface WelcomeOverlayProps {
  open: boolean;
  onBrowseTemplates: () => void;
  onStartBlank: () => void;
  onSkip: () => void;
}

const HIGHLIGHTS = [
  {
    icon: MousePointerClick,
    title: __('Add options shoppers can pick', 'flexa-extra'),
    body: __('Text, choices, swatches, buttons, date and colour pickers.', 'flexa-extra'),
  },
  {
    icon: Tag,
    title: __('Charge for the extras', 'flexa-extra'),
    body: __('Per-option fees, recomputed on the server so totals always match.', 'flexa-extra'),
  },
  {
    icon: PencilRuler,
    title: __('Assign to the right products', 'flexa-extra'),
    body: __('All products, a hand-picked list, or by category, tag and more.', 'flexa-extra'),
  },
];

/**
 * First-run welcome. Shown once on the Option Sets screen while the guide is
 * still pending. It only frames the choice: pick a template (the fast path),
 * start blank, or skip. Everything after this reuses the normal builder.
 */
export function WelcomeOverlay({ open, onBrowseTemplates, onStartBlank, onSkip }: WelcomeOverlayProps) {
  return (
    <AnimatePresence>
      {open && (
        <motion.div
          className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-6"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          role="dialog"
          aria-modal="true"
          aria-label={__('Welcome to Flexa Extra', 'flexa-extra')}
        >
          <motion.div
            className="bg-card border-border my-auto w-full max-w-2xl rounded-xl border p-8 text-center shadow-lg"
            initial={{ opacity: 0, scale: 0.97, y: 8 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.97, y: 8 }}
            transition={{ duration: 0.15 }}
          >
            <div className="bg-primary/10 text-primary mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full">
              <Sparkles className="h-6 w-6" />
            </div>
            <h2 className="text-2xl font-bold tracking-tight">
              {__('Welcome to Flexa Extra', 'flexa-extra')}
            </h2>
            <p className="text-muted-foreground mx-auto mt-2 max-w-md">
              {__(
                'Add extra options to your products and start charging for them. The quickest way is to begin from a ready-made template.',
                'flexa-extra',
              )}
            </p>

            <div className="mt-7 grid gap-4 text-left sm:grid-cols-3">
              {HIGHLIGHTS.map(({ icon: Icon, title, body }) => (
                <div key={title} className="flex flex-col gap-1.5">
                  <span className="text-primary flex items-center gap-2 text-sm font-medium">
                    <Icon className="h-4 w-4" />
                    {title}
                  </span>
                  <span className="text-muted-foreground text-xs leading-relaxed">{body}</span>
                </div>
              ))}
            </div>

            <div className="mt-8 flex flex-col items-center justify-center gap-2 sm:flex-row">
              <Button size="lg" onClick={onBrowseTemplates}>
                <LayoutTemplate className="h-4 w-4" />
                {__('Browse templates', 'flexa-extra')}
              </Button>
              <Button variant="outline" size="lg" onClick={onStartBlank}>
                {__('Start from scratch', 'flexa-extra')}
              </Button>
            </div>

            <button
              type="button"
              onClick={onSkip}
              className="text-muted-foreground hover:text-foreground mt-4 text-sm underline-offset-4 hover:underline"
            >
              {__('Skip for now', 'flexa-extra')}
            </button>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
