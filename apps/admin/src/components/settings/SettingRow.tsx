import { ReactNode } from 'react';
import { LucideIcon } from 'lucide-react';

interface SettingRowProps {
  icon: LucideIcon;
  label: string;
  description?: string;
  control: ReactNode;
}

export function SettingRow({ icon: Icon, label, description, control }: SettingRowProps) {
  return (
    <div className="border-border flex items-center gap-3 border-b px-5 py-4 last:border-b-0">
      <div className="bg-muted text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
        <Icon className="h-4 w-4" />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-foreground font-medium">{label}</p>
        {description && <p className="text-muted-foreground text-xs">{description}</p>}
      </div>
      <div className="shrink-0">{control}</div>
    </div>
  );
}
