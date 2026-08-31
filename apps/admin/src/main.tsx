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
  createRoot(container).render(
    <React.StrictMode>
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={getManagerRouter()} />
      </QueryClientProvider>
    </React.StrictMode>,
  );
}
