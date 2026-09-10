import { __ } from '@wordpress/i18n';
import { ImagePlus, X } from 'lucide-react';

import { cn } from '@/lib/utils';

interface ImageFieldProps {
  url: string;
  onPick: (id: number, url: string) => void;
  onClear: () => void;
  className?: string;
}

interface MediaAttachment {
  id: number;
  sizes?: { thumbnail?: { url: string } };
  url: string;
}

interface MediaFrame {
  on: (event: string, cb: () => void) => void;
  open: () => void;
  state: () => { get: (key: string) => { first: () => { toJSON: () => MediaAttachment } } };
}

type MediaFactory = (config: Record<string, unknown>) => MediaFrame;

/**
 * Per-term image control backed by the WordPress media library (`wp.media`),
 * the same modal the rest of wp-admin uses. Shows a thumbnail once an image is
 * picked, with a reset button that clears back to "no image".
 */
export function ImageField({ url, onPick, onClear, className }: ImageFieldProps) {
  const openPicker = () => {
    const media = (window.wp as { media?: MediaFactory })?.media;
    if (typeof media !== 'function') {
      return;
    }

    const frame = media({
      title: __('Select an image', 'flexa-extra'),
      button: { text: __('Use this image', 'flexa-extra') },
      library: { type: 'image' },
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      const thumb = attachment.sizes?.thumbnail?.url ?? attachment.url;
      onPick(attachment.id, thumb);
    });

    frame.open();
  };

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <button
        type="button"
        onClick={openPicker}
        className="border-input bg-background hover:bg-muted flex h-10 w-10 items-center justify-center overflow-hidden rounded-md border"
        aria-label={url ? __('Change image', 'flexa-extra') : __('Choose image', 'flexa-extra')}
      >
        {url ? (
          <img src={url} alt="" className="h-full w-full object-cover" />
        ) : (
          <ImagePlus className="text-muted-foreground h-4 w-4" />
        )}
      </button>
      <span className="text-muted-foreground text-xs">
        {url ? __('Image set', 'flexa-extra') : __('No image', 'flexa-extra')}
      </span>
      {url && (
        <button
          type="button"
          onClick={onClear}
          aria-label={__('Remove image', 'flexa-extra')}
          className="text-muted-foreground hover:text-foreground hover:bg-muted flex h-6 w-6 items-center justify-center rounded-md transition-colors"
        >
          <X className="h-3.5 w-3.5" />
        </button>
      )}
    </div>
  );
}
