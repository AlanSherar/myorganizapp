export type Recurrence = 'daily' | 'weekly' | 'monthly' | 'yearly';

const atStartOfDay = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0, 0);
const atEndOfDay = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59, 999);

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

