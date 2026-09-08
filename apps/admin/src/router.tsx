import { createHashRouter, redirect, type RouteObject } from 'react-router-dom';

import AppLayout from './AppLayout';
import NotFoundPage from './pages/NotFound';
import OptionSets from './pages/option-sets/OptionSets';
import OptionSetBuilder from './pages/option-sets/builder/OptionSetBuilder';
import Analytics from './pages/analytics/Analytics';
import Migration from './pages/migration/Migration';
import Settings from './pages/settings/Settings';

const baseRoutes: RouteObject[] = [
  {
    index: true,
    loader: () => redirect('/option-sets'),
  },
  {
    path: 'option-sets',
    element: <OptionSets />,
  },
  {
    path: 'option-sets/new',
    element: <OptionSetBuilder />,
  },
  {
    path: 'option-sets/:id',
    element: <OptionSetBuilder />,
  },
  {
    path: 'analytics',
    element: <Analytics />,
  },
  {
    path: 'import',
    element: <Migration />,
  },
  {
    path: 'settings',
    element: <Settings />,
  },
];

export function getManagerRouter() {
  return createHashRouter([
    {
      path: '/',
      element: <AppLayout />,
      errorElement: <NotFoundPage />,
      children: baseRoutes,
    },
  ]);
}
