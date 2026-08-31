import * as React from 'react';

import { cn } from '@/lib/utils';

export interface SelectOption {
  label: string;
  value: string;
}

interface SelectProps extends React.ComponentProps<'select'> {
  options: SelectOption[];
}

const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
  ({ className, options, ...props }, ref) => {
    return (
      <select
        ref={ref}
        className={cn(
          'border-input bg-background text-foreground h-9 rounded-md border px-3 text-sm shadow-sm outline-none transition-colors',
          'focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary-accent',
          className,
        )}
        {...props}
      >
        {options.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    );
  },
);
Select.displayName = 'Select';

export { Select };
