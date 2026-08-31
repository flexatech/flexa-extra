import { __ } from '@wordpress/i18n';
import { X } from 'lucide-react';

import { cn } from '@/lib/utils';

interface ColorFieldProps {
  value: string;
  onChange: (value: string) => void;
  className?: string;
}

/**
 * Optional color control. An empty value means "inherit the theme color", so a
 * reset button clears back to '' rather than forcing a concrete hex. The native
 * color input falls back to a neutral gray while empty.
 */
export function ColorField({ value, onChange, className }: ColorFieldProps) {
  const hasColor = value !== '';

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <input
        type="color"
        aria-label={__('Pick color', 'flexa-extra')}
        value={hasColor ? value : '#cccccc'}
        onChange={(e) => onChange(e.target.value)}
        className="border-input h-8 w-10 cursor-pointer rounded-md border bg-transparent p-0.5"
      />
      <span className="text-muted-foreground min-w-[68px] font-mono text-xs">
        {hasColor ? value : __('Default', 'flexa-extra')}
      </span>
      {hasColor && (
        <button
          type="button"
          onClick={() => onChange('')}
          aria-label={__('Reset to theme default', 'flexa-extra')}
          className="text-muted-foreground hover:text-foreground hover:bg-muted flex h-6 w-6 items-center justify-center rounded-md transition-colors"
        >
          <X className="h-3.5 w-3.5" />
        </button>
      )}
    </div>
  );
}
