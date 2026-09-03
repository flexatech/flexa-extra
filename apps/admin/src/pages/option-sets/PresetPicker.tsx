import { __ } from '@wordpress/i18n';
import { AnimatePresence, motion } from 'framer-motion';
import { Loader2, X } from 'lucide-react';
import { useEffect } from 'react';

import { getPresets, PresetDefinition } from '@/lib/fields/presets';
import { Button } from '@/components/ui/button';

interface PresetPickerProps {
  open: boolean;
  pendingId: string | null;
  onClose: () => void;
  onSelectPreset: (preset: PresetDefinition) => void;
}

export function PresetPicker({ open, pendingId, onClose, onSelectPreset }: PresetPickerProps) {
  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !pendingId) onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, pendingId, onClose]);

  const presets = getPresets();

  return (
    <AnimatePresence>
      {open && (
        <motion.div
          className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-6"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          onClick={() => !pendingId && onClose()}
          role="dialog"
          aria-modal="true"
          aria-label={__('Start from a template', 'flexa-extra')}
        >
          <motion.div
            className="bg-card border-border my-auto w-full max-w-4xl rounded-xl border p-6 shadow-lg"
            initial={{ opacity: 0, scale: 0.97, y: 8 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.97, y: 8 }}
            transition={{ duration: 0.15 }}
            onClick={(e) => e.stopPropagation()}
          >
            <div className="mb-5 flex items-start justify-between">
              <div>
                <h2 className="text-lg font-semibold">
                  {__('Start from a template', 'flexa-extra')}
                </h2>
                <p className="text-muted-foreground mt-1 text-sm">
                  {__(
                    'Pick a starting point. It creates a draft you can edit before publishing.',
                    'flexa-extra',
                  )}
                </p>
              </div>
              <Button variant="ghost" size="icon" onClick={onClose} disabled={!!pendingId}>
                <X className="h-4 w-4" />
              </Button>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {presets.map((preset) => (
                <PresetCard
                  key={preset.id}
                  preset={preset}
                  busy={pendingId === preset.id}
                  disabled={!!pendingId}
                  onSelect={() => onSelectPreset(preset)}
                />
              ))}
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

function PresetCard({
  preset,
  busy,
  disabled,
  onSelect,
}: {
  preset: PresetDefinition;
  busy: boolean;
  disabled: boolean;
  onSelect: () => void;
}) {
  const fieldLabels = preset
    .build()
    .fields.map((f) => f.label)
    .filter(Boolean);

  return (
    <button
      type="button"
      onClick={onSelect}
      disabled={disabled}
      className="border-border hover:border-primary hover:bg-muted/40 group relative flex flex-col rounded-lg border p-4 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60"
    >
      <span className="font-medium">{preset.title}</span>
      <span className="text-muted-foreground mt-1 text-sm">{preset.description}</span>
      <span className="mt-3 flex flex-wrap gap-1.5">
        {fieldLabels.map((label, i) => (
          <span
            key={i}
            className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs"
          >
            {label}
          </span>
        ))}
      </span>
      {busy && (
        <span className="bg-card/60 absolute inset-0 flex items-center justify-center rounded-lg">
          <Loader2 className="text-primary h-5 w-5 animate-spin" />
        </span>
      )}
    </button>
  );
}
