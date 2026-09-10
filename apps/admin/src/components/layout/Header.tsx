import { useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { BarChart3, Bolt, DownloadCloud, LayoutGrid, Palette, ShoppingBag } from 'lucide-react';
import { useLocation, useNavigate, type To } from 'react-router-dom';

import { cn } from '@/lib/utils';
import { useScrolled } from '@/hooks/useScrolled';
import { HeaderNavMenuItem, HeaderNavMenuList } from '@/components/ui/navmenu-header';

interface NavItem {
  to: string;
  icon: typeof LayoutGrid;
  label: string;
  isActive: (pathname: string) => boolean;
}

const SWATCHES_ROOT = '/variation-swatches';

// The app has two areas, entered from the WP "Flexa" submenu. Each shows only
// its own tabs so Product Options and Variation Swatches stay separate.
const PRODUCT_NAV: NavItem[] = [
  { to: '/option-sets', icon: LayoutGrid, label: __('Option Sets', 'flexa-extra'), isActive: (p) => p.startsWith('/option-sets') },
  { to: '/analytics', icon: BarChart3, label: __('Analytics', 'flexa-extra'), isActive: (p) => p.startsWith('/analytics') },
  { to: '/import', icon: DownloadCloud, label: __('Import', 'flexa-extra'), isActive: (p) => p.startsWith('/import') },
  { to: '/settings', icon: Bolt, label: __('Settings', 'flexa-extra'), isActive: (p) => p.startsWith('/settings') },
];

const SWATCHES_NAV: NavItem[] = [
  { to: SWATCHES_ROOT, icon: Palette, label: __('Swatches', 'flexa-extra'), isActive: (p) => p === SWATCHES_ROOT },
  { to: `${SWATCHES_ROOT}/settings`, icon: Bolt, label: __('Settings', 'flexa-extra'), isActive: (p) => p.startsWith(`${SWATCHES_ROOT}/settings`) },
  { to: `${SWATCHES_ROOT}/analytics`, icon: BarChart3, label: __('Analytics', 'flexa-extra'), isActive: (p) => p.startsWith(`${SWATCHES_ROOT}/analytics`) },
];

export default function Header() {
  const navigate = useNavigate();
  const scrolled = useScrolled();
  const { pathname } = useLocation();
  const handleNavClick = useCallback((to: To) => navigate(to), [navigate]);

  const inSwatches = pathname.startsWith(SWATCHES_ROOT);
  const items = inSwatches ? SWATCHES_NAV : PRODUCT_NAV;
  const subtitle = inSwatches ? __('Variation Swatches', 'flexa-extra') : __('Extra Product Options', 'flexa-extra');

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
          <span className="text-muted-foreground text-[10px]">{subtitle}</span>
        </div>
      </div>

      {/* Navigation */}
      <HeaderNavMenuList className="h-full flex-none justify-start gap-6">
        {items.map(({ to, icon: Icon, label, isActive }) => (
          <HeaderNavMenuItem
            key={to}
            onClick={() => handleNavClick(to)}
            className={cn('h-full font-semibold', isActive(pathname) && activeItemClass)}
          >
            <Icon className="size-5" />
            <span>{label}</span>
          </HeaderNavMenuItem>
        ))}
      </HeaderNavMenuList>
    </header>
  );
}
