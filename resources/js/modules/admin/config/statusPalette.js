/**
 * Admin UI semantic status palette.
 * Used by AdminBadge and status indicators across modules.
 */
export const statusVariants = {
    success: 'bg-emerald-100 text-emerald-800',
    warning: 'bg-amber-100 text-amber-800',
    error: 'bg-rose-100 text-rose-800',
    info: 'bg-sky-100 text-sky-800',
    neutral: 'bg-slate-200 text-slate-700',
};

export const healthStatusMap = {
    ok: { variant: 'success', label: 'Healthy' },
    warn: { variant: 'warning', label: 'Degraded' },
    fail: { variant: 'error', label: 'Down' },
};

export const alertSeverityMap = {
    critical: { variant: 'error', label: 'Critical' },
    warning: { variant: 'warning', label: 'Warning' },
};
