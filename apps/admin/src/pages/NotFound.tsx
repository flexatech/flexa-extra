import { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useLocation } from 'react-router-dom';

const NotFound = () => {
  const location = useLocation();

  useEffect(() => {
    console.error('404: route not found:', location.pathname);
  }, [location.pathname]);

  return (
    <div className="mx-auto mt-8 max-w-7xl px-6">
      <div className="flex flex-col items-center justify-center py-20">
        <div className="bg-muted text-primary mb-6 flex h-24 w-24 items-center justify-center rounded-full">
          <span className="text-5xl font-extrabold">404</span>
        </div>
        <h2 className="mb-2 text-2xl font-semibold">{__('Page Not Found', 'flexa-extra')}</h2>
        <a
          href="#/option-sets"
          className="bg-primary hover:bg-primary-accent mt-4 inline-block rounded-md px-6 py-2 text-sm font-medium text-white transition-colors"
        >
          {__('Go to Option Sets', 'flexa-extra')}
        </a>
      </div>
    </div>
  );
};

export default NotFound;
