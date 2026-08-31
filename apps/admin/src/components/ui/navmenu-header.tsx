import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';

import { cn } from '@/lib/utils';

interface HeaderNavMenuListProps extends React.ComponentProps<'ul'> {
  asChild?: boolean;
}

const HeaderNavMenuList = React.forwardRef<HTMLUListElement, HeaderNavMenuListProps>(
  ({ className, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'ul';
    return (
      <Comp
        data-slot="header-navigation-menu-list"
        className={cn('flex h-9 flex-1 list-none items-stretch justify-center gap-8', className)}
        {...props}
        ref={ref}
      />
    );
  },
);
HeaderNavMenuList.displayName = 'HeaderNavMenuList';

interface HeaderNavMenuItemProps extends React.ComponentProps<'li'> {
  asChild?: boolean;
}

const HeaderNavMenuItem = React.forwardRef<HTMLLIElement, HeaderNavMenuItemProps>(
  ({ className, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'li';
    return (
      <Comp
        data-slot="header-navigation-menu-item"
        className={cn(
          'text-foreground hover:text-primary flex cursor-pointer items-center gap-1.5 border-b-3 border-solid border-transparent text-sm outline-none transition-colors',
          className,
        )}
        {...props}
        ref={ref}
      />
    );
  },
);
HeaderNavMenuItem.displayName = 'HeaderNavMenuItem';

export { HeaderNavMenuList, HeaderNavMenuItem };
