import * as React from "react"

import { cn } from "@/lib/utils"

function Input({
  className,
  type,
  autoComplete = "off",
  ...props
}: React.ComponentProps<"input">) {
  const disableBrowserFill = autoComplete === "off";

  return (
    <input
      type={type}
      autoComplete={autoComplete}
      autoCorrect={disableBrowserFill ? "off" : undefined}
      autoCapitalize={disableBrowserFill ? "none" : undefined}
      data-1p-ignore={disableBrowserFill || undefined}
      data-lpignore={disableBrowserFill ? "true" : undefined}
      data-slot="input"
      className={cn(
        "border-border-strong file:text-foreground placeholder:text-text-subtle selection:bg-primary selection:text-primary-foreground flex h-9 w-full min-w-0 rounded-lg border bg-card px-3 py-1 text-base shadow-none transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
        "focus-visible:border-primary focus-visible:ring-primary-50 focus-visible:ring-[3px]",
        "aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
        className
      )}
      {...props}
    />
  )
}

export { Input }
