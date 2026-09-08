import * as React from 'react';

import { cn } from '@/lib/utils';

export interface SelectOption {
  label: string;
  value: string;
}

interface SelectProps extends React.ComponentProps<'select'> {
  options: SelectOption[];
}

// A chevron drawn by us instead of the browser's native arrow. The native arrow
// sits flush against the text on narrow selects; with appearance-none we own the
// spacing and reserve room for it with pr-8, so text never crowds the icon.
const CHEVRON =
  "url(\"data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='16'%20height='16'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='%236b7280'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Cpath%20d='m6%209%206%206%206-6'/%3E%3C/svg%3E\")";

const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
  ({ className, options, style, ...props }, ref) => {
    return (
      <select
        ref={ref}
        style={{
          backgroundImage: CHEVRON,
          backgroundRepeat: 'no-repeat',
          backgroundPosition: 'right 0.625rem center',
          backgroundSize: '0.75rem',
          ...style,
        }}
        className={cn(
          'border-input bg-background text-foreground h-9 appearance-none rounded-md border pl-3 pr-8 text-sm shadow-sm outline-none transition-colors',
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
