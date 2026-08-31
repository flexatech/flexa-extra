import { ReactNode } from 'react';
import { motion } from 'framer-motion';
import { LucideIcon } from 'lucide-react';

interface TabCardProps {
  icon: LucideIcon;
  title: string;
  description: string;
  children: ReactNode;
}

export function TabCard({ icon: Icon, title, description, children }: TabCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -10 }}
      transition={{ duration: 0.2 }}
      className="border-border bg-card overflow-hidden rounded-xl border shadow-sm"
    >
      <div className="border-border flex items-center gap-3 border-b px-5 py-4">
        <div className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-lg">
          <Icon className="h-5 w-5" />
        </div>
        <div>
          <h2 className="text-lg font-semibold">{title}</h2>
          <p className="text-muted-foreground text-sm">{description}</p>
        </div>
      </div>
      <div>{children}</div>
    </motion.div>
  );
}
