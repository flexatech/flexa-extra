import './main.css';

import React from 'react';
import { QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router-dom';

import { getManagerRouter } from '@/router';
import { showToast } from '@/components/custom/showToast';

const queryClient = new QueryClient({
  queryCache: new QueryCache({
    onError: (error: Error) => {
      showToast.error(
        `${__('An error occurred', 'flexa-extra')}: ${error.message}`,
      );
    },
  }),
  defaultOptions: {
    queries: {
      retry: false,
      refetchOnWindowFocus: false,
      staleTime: 5 * 60 * 1000,
    },
  },
});

const container = document.getElementById('flexa-extra-admin-root');

if (container) {
  // A submenu can request an initial screen via data-initial-route. Honour it
  // only on a fresh load (no hash yet) so it never fights in-app navigation.
  const initialRoute = container.dataset.initialRoute;
  const hash = window.location.hash.replace(/^#/, '');
  if (initialRoute && (hash === '' || hash === '/')) {
    window.location.hash = `#${initialRoute}`;
  }

  createRoot(container).render(
    <React.StrictMode>
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={getManagerRouter()} />
      </QueryClientProvider>
    </React.StrictMode>,
  );
}
