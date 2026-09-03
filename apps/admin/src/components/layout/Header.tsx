import { useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { Bolt, LayoutGrid, ShoppingBag } from 'lucide-react';
import { useMatch, useNavigate } from 'react-router-dom';

import { cn } from '@/lib/utils';
import { useScrolled } from '@/hooks/useScrolled';
import { HeaderNavMenuItem, HeaderNavMenuList } from '@/components/ui/navmenu-header';

const NAV_ITEMS = [
  { path: '/option-sets/*', to: '/option-sets', icon: LayoutGrid, label: __('Option Sets', 'flexa-extra') },
  { path: '/settings/*', to: '/settings', icon: Bolt, label: __('Settings', 'flexa-extra') },
];

export default function Header() {
  const navigate = useNavigate();
  const scrolled = useScrolled();
  const handleNavClick = useCallback((to: string) => navigate(to), [navigate]);

  const activeItemClass = 'text-primary border-primary hover:text-primary-accent';

  return (
    <header
      className={cn(
        'bg-background sticky top-8 z-40 flex h-[56px] w-full items-center gap-6 border-b border-border px-4 transition-shadow duration-300',
        scrolled ? 'shadow-[0_8px_8px_0_rgba(85,93,102,0.15)]' : 'shadow-none',
      )}
    >
      {/* Logo */}
      <div className="flex h-full items-center gap-3 pr-4">
        <div className="bg-sidebar-primary text-sidebar-primary-foreground flex h-8 w-8 items-center justify-center rounded-lg">
          <ShoppingBag className="h-4 w-4" />
        </div>
        <div className="flex flex-col">
          <span className="text-foreground text-sm font-semibold">Flexa Extra</span>
          <span className="text-muted-foreground text-[10px]">
            {__('Extra Product Options', 'flexa-extra')}
          </span>
        </div>
      </div>

      {/* Navigation */}
      <HeaderNavMenuList className="h-full flex-none justify-start gap-6">
        {NAV_ITEMS.map(({ path, to, icon: Icon, label }) => {
          // eslint-disable-next-line react-hooks/rules-of-hooks
          const isActive = !!useMatch({ path });
          return (
            <HeaderNavMenuItem
              key={to}
              onClick={() => handleNavClick(to)}
              className={cn('h-full font-semibold', isActive && activeItemClass)}
            >
              <Icon className="size-5" />
              <span>{label}</span>
            </HeaderNavMenuItem>
          );
        })}
      </HeaderNavMenuList>
    </header>
  );
}
