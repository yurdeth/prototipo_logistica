// Utilidades y estilos compartidos por los formularios (Despachos, Envíos, etc.)

export const MIN = 60_000;
export const H = 3_600_000;

export type Aviso = { texto: string; grave: boolean };
export type ChecklistItem = { label: string; ok: boolean };

/* ───────── Clases reutilizables ───────── */
export const inputClase =
    'h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 disabled:cursor-not-allowed disabled:bg-slate-100';
export const labelClase = 'mb-1.5 block text-sm font-medium text-slate-700';
export const errorClase = 'mt-1 text-xs text-red-600';

/* ───────── Color / formato ───────── */
// Fondo suave para cualquier color CSS (hex, rgb, hsl...).
export const soft = (color: string, pct = 12) =>
    `color-mix(in srgb, ${color} ${pct}%, transparent)`;

const pad = (n: number) => String(n).padStart(2, '0');

// Formato para <input type="datetime-local"> en hora local.
export const aInputLocal = (f: Date) =>
    `${f.getFullYear()}-${pad(f.getMonth() + 1)}-${pad(f.getDate())}T${pad(f.getHours())}:${pad(f.getMinutes())}`;

export const fmtHora = (ms: number) =>
    Number.isNaN(ms)
        ? '—'
        : new Date(ms).toLocaleTimeString('es-SV', {
              hour: '2-digit',
              minute: '2-digit',
          });

export const fmtNumero = (n: number, decimales = 1) =>
    n.toLocaleString('es-SV', { maximumFractionDigits: decimales });

export const esPositivo = (v: number | string | null | undefined) =>
    v !== '' && v != null && Number.isFinite(Number(v)) && Number(v) > 0;
