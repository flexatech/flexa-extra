interface Props {
  label: string;
  hint?: string;
  children: React.ReactNode;
}

export function Labeled({ label, hint, children }: Props) {
  return (
    <div className="space-y-1.5">
      <label className="text-foreground block text-sm font-medium">{label}</label>
      {children}
      {hint ? <p className="text-muted-foreground text-xs">{hint}</p> : null}
    </div>
  );
}
