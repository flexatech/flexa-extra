import { Outlet } from 'react-router-dom';

import { Toaster } from '@/components/ui/sonner';

import Header from './components/layout/Header';

export default function AppLayout() {
  return (
    <div className="bg-background min-h-screen">
      <Header />
      <main>
        <Toaster />
        <Outlet />
      </main>
    </div>
  );
}
