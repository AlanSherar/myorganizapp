export type Recurrence = 'daily' | 'weekly' | 'monthly' | 'yearly';
export type DueRule = {
  time: string;
  dayOfWeek?: number;
  dayOfMonth?: number;
  month?: number;
};

const atStartOfDay = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0, 0);
const atEndOfDay = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59, 999);

const parseTime = (time: string) => {
  const [h, m] = time.split(':').map((n) => parseInt(n, 10));
  return { h: Number.isFinite(h) ? h : 23, m: Number.isFinite(m) ? m : 59 };
};

export const getIntervalBounds = (recurrence: Recurrence, date: Date) => {
  if (recurrence === 'daily') {
    return { start: atStartOfDay(date), end: atEndOfDay(date) };
  }

  if (recurrence === 'weekly') {
    const day = date.getDay();
    const sinceMonday = (day + 6) % 7;
    const start = atStartOfDay(new Date(date.getFullYear(), date.getMonth(), date.getDate() - sinceMonday));
    const end = new Date(start);
    end.setDate(end.getDate() + 7);
    end.setMilliseconds(end.getMilliseconds() - 1);
    return { start, end };
  }

  if (recurrence === 'monthly') {
    const start = new Date(date.getFullYear(), date.getMonth(), 1, 0, 0, 0, 0);
    const end = new Date(date.getFullYear(), date.getMonth() + 1, 1, 0, 0, 0, 0);
    end.setMilliseconds(end.getMilliseconds() - 1);
    return { start, end };
  }

  const start = new Date(date.getFullYear(), 0, 1, 0, 0, 0, 0);
  const end = new Date(date.getFullYear() + 1, 0, 1, 0, 0, 0, 0);
  end.setMilliseconds(end.getMilliseconds() - 1);
  return { start, end };
};

export const getNextIntervalBounds = (recurrence: Recurrence, currentIntervalStart: Date) => {
  const base = new Date(currentIntervalStart);
  if (recurrence === 'daily') base.setDate(base.getDate() + 1);
  if (recurrence === 'weekly') base.setDate(base.getDate() + 7);
  if (recurrence === 'monthly') base.setMonth(base.getMonth() + 1);
  if (recurrence === 'yearly') base.setFullYear(base.getFullYear() + 1);
  return getIntervalBounds(recurrence, base);
};

const clampDayOfMonth = (year: number, month: number, day: number) => {
  const last = new Date(year, month + 1, 0).getDate();
  return Math.max(1, Math.min(last, day));
};

export const deriveDueRuleFromDate = (recurrence: Recurrence, dueAt: Date): DueRule => {
  const hh = String(dueAt.getHours()).padStart(2, '0');
  const mm = String(dueAt.getMinutes()).padStart(2, '0');
  const time = `${hh}:${mm}`;

  if (recurrence === 'daily') return { time };
  if (recurrence === 'weekly') return { time, dayOfWeek: dueAt.getDay() };
  if (recurrence === 'monthly') return { time, dayOfMonth: dueAt.getDate() };
  return { time, month: dueAt.getMonth(), dayOfMonth: dueAt.getDate() };
};

export const computeDueAtForInterval = (recurrence: Recurrence, intervalStart: Date, rule: DueRule) => {
  const { h, m } = parseTime(rule.time);
  const y = intervalStart.getFullYear();
  const mo = intervalStart.getMonth();

  if (recurrence === 'daily') {
    return new Date(y, mo, intervalStart.getDate(), h, m, 0, 0);
  }

  if (recurrence === 'weekly') {
    const startDow = intervalStart.getDay();
    const targetDow = rule.dayOfWeek ?? startDow;
    const diff = (targetDow - startDow + 7) % 7;
    const d = new Date(intervalStart);
    d.setDate(d.getDate() + diff);
    d.setHours(h, m, 0, 0);
    return d;
  }

  if (recurrence === 'monthly') {
    const day = clampDayOfMonth(y, mo, rule.dayOfMonth ?? 1);
    return new Date(y, mo, day, h, m, 0, 0);
  }

  const month = rule.month ?? 0;
  const day = clampDayOfMonth(y, month, rule.dayOfMonth ?? 1);
  return new Date(y, month, day, h, m, 0, 0);
};
