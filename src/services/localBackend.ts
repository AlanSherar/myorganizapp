import type {
  Task,
  CreateTaskInput,
  Transaction,
  CreateTransactionInput,
  Routine,
  CreateRoutineInput,
  RoutineCheckIn,
  TaskRecurrence,
  TaskPriority,
  ITaskService,
  IFinanceService,
  IRoutineService,
} from './types.js'

const LOCAL_DB_STORAGE_KEY = 'ORG_LOCAL_DB_V1'
const MAX_BACKFILL_INTERVALS = 60

// =========================
// Local Database State
// =========================
type TaskSeriesDueRule = {
  time: string
  dayOfWeek?: number
  dayOfMonth?: number
  month?: number
}

type LocalTaskSeries = {
  id: string
  userId: string
  title: string
  description?: string
  recurrence: TaskRecurrence
  priority: TaskPriority
  keepIfMissed: boolean
  dueRule: TaskSeriesDueRule
  startAt: string
  stopAt?: string
  lastGeneratedIntervalStart?: string
  legacySeriesId?: string
  createdAt: string
  updatedAt: string
}

type LocalDatabaseState = {
  tasks: Task[]
  taskSeries: LocalTaskSeries[]
  transactions: Transaction[]
  routines: Routine[]
  routineCheckIns: RoutineCheckIn[]
}

// =========================
// Recurrence Utils (copied from backend)
// =========================
type Recurrence = 'daily' | 'weekly' | 'monthly' | 'yearly'
type DueRule = TaskSeriesDueRule

const atStartOfDay = (d: Date) =>
  new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0, 0)
const atEndOfDay = (d: Date) =>
  new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59, 999)

const parseTime = (time: string) => {
  const [h, m] = time.split(':').map((n) => parseInt(n, 10))
  return { h: Number.isFinite(h) ? h : 23, m: Number.isFinite(m) ? m : 59 }
}

const getIntervalBounds = (recurrence: Recurrence, date: Date) => {
  if (recurrence === 'daily') return { start: atStartOfDay(date), end: atEndOfDay(date) }
  if (recurrence === 'weekly') {
    const day = date.getDay()
    const sinceMonday = (day + 6) % 7
    const start = atStartOfDay(
      new Date(date.getFullYear(), date.getMonth(), date.getDate() - sinceMonday),
    )
    const end = new Date(start)
    end.setDate(end.getDate() + 7)
    end.setMilliseconds(end.getMilliseconds() - 1)
    return { start, end }
  }
  if (recurrence === 'monthly') {
    const start = new Date(date.getFullYear(), date.getMonth(), 1, 0, 0, 0, 0)
    const end = new Date(date.getFullYear(), date.getMonth() + 1, 1, 0, 0, 0, 0)
    end.setMilliseconds(end.getMilliseconds() - 1)
    return { start, end }
  }
  const start = new Date(date.getFullYear(), 0, 1, 0, 0, 0, 0)
  const end = new Date(date.getFullYear() + 1, 0, 1, 0, 0, 0, 0)
  end.setMilliseconds(end.getMilliseconds() - 1)
  return { start, end }
}

const getNextIntervalBounds = (recurrence: Recurrence, currentIntervalStart: Date) => {
  const base = new Date(currentIntervalStart)
  if (recurrence === 'daily') base.setDate(base.getDate() + 1)
  if (recurrence === 'weekly') base.setDate(base.getDate() + 7)
  if (recurrence === 'monthly') base.setMonth(base.getMonth() + 1)
  if (recurrence === 'yearly') base.setFullYear(base.getFullYear() + 1)
  return getIntervalBounds(recurrence, base)
}

const clampDayOfMonth = (year: number, month: number, day: number) => {
  const last = new Date(year, month + 1, 0).getDate()
  return Math.max(1, Math.min(last, day))
}

const deriveDueRuleFromDate = (recurrence: Recurrence, dueAt: Date): DueRule => {
  const hh = String(dueAt.getHours()).padStart(2, '0')
  const mm = String(dueAt.getMinutes()).padStart(2, '0')
  const time = `${hh}:${mm}`
  if (recurrence === 'daily') return { time }
  if (recurrence === 'weekly') return { time, dayOfWeek: dueAt.getDay() }
  if (recurrence === 'monthly') return { time, dayOfMonth: dueAt.getDate() }
  return { time, month: dueAt.getMonth(), dayOfMonth: dueAt.getDate() }
}

const computeDueAtForInterval = (
  recurrence: Recurrence,
  intervalStart: Date,
  rule: DueRule,
) => {
  const { h, m } = parseTime(rule.time)
  const y = intervalStart.getFullYear()
  const mo = intervalStart.getMonth()
  if (recurrence === 'daily') return new Date(y, mo, intervalStart.getDate(), h, m, 0, 0)
  if (recurrence === 'weekly') {
    const startDow = intervalStart.getDay()
    const targetDow = rule.dayOfWeek ?? startDow
    const diff = (targetDow - startDow + 7) % 7
    const d = new Date(intervalStart)
    d.setDate(d.getDate() + diff)
    d.setHours(h, m, 0, 0)
    return d
  }
  if (recurrence === 'monthly') {
    const day = clampDayOfMonth(y, mo, rule.dayOfMonth ?? 1)
    return new Date(y, mo, day, h, m, 0, 0)
  }
  const month = rule.month ?? 0
  const day = clampDayOfMonth(y, month, rule.dayOfMonth ?? 1)
  return new Date(y, month, day, h, m, 0, 0)
}

// =========================
// State Helpers
// =========================
function nowIso() {
  return new Date().toISOString()
}

function createInitialState(): LocalDatabaseState {
  return {
    tasks: [],
    taskSeries: [],
    transactions: [],
    routines: [],
    routineCheckIns: [],
  }
}

function readState(): LocalDatabaseState {
  if (typeof window === 'undefined' || !window.localStorage) return createInitialState()
  const raw = localStorage.getItem(LOCAL_DB_STORAGE_KEY)
  if (!raw) return createInitialState()
  try {
    return JSON.parse(raw) as LocalDatabaseState
  } catch {
    return createInitialState()
  }
}

function writeState(state: LocalDatabaseState) {
  if (typeof window === 'undefined' || !window.localStorage) return
  localStorage.setItem(LOCAL_DB_STORAGE_KEY, JSON.stringify(state))
}

function updateState(updater: (s: LocalDatabaseState) => LocalDatabaseState) {
  const next = updater(readState())
  writeState(next)
  return next
}

function uid(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return crypto.randomUUID()
  return 'id-' + Math.random().toString(36).slice(2) + Date.now().toString(36)
}

function sortTasksByDueDate(tasks: Task[]): Task[] {
  return [...tasks].sort((a, b) => {
    const ad = a.dueDate ? new Date(a.dueDate).getTime() : Infinity
    const bd = b.dueDate ? new Date(b.dueDate).getTime() : Infinity
    return ad - bd
  })
}

function sortTransactionsByDateDesc(items: Transaction[]): Transaction[] {
  return [...items].sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
}

// =========================
// Task Business Logic (from backend taskService)
// =========================
function migrateLegacyRecurringTasks(state: LocalDatabaseState): LocalDatabaseState {
  const legacyIds = Array.from(
    new Set(
      state.tasks
        .filter((t) => t.type === 'recurring' && t.seriesId && !t.seriesId.startsWith('ls_'))
        .map((t) => t.seriesId!),
    ),
  )

  const nextState = { ...state, taskSeries: [...state.taskSeries], tasks: [...state.tasks] }

  for (const legacyId of legacyIds) {
    if (nextState.taskSeries.some((s) => s.legacySeriesId === legacyId)) continue
    const template = nextState.tasks.find(
      (t) => t.type === 'recurring' && t.seriesId === legacyId,
    )
    if (!template || !template.recurrence) continue

    const recurrence = template.recurrence as Recurrence
    const baseDate = template.dueDate ? new Date(template.dueDate) : new Date()
    const bounds =
      template.intervalStart && template.intervalEnd
        ? {
            start: new Date(template.intervalStart),
            end: new Date(template.intervalEnd),
          }
        : getIntervalBounds(recurrence, baseDate)

    const seriesId = 'ls_' + uid()

    const series: LocalTaskSeries = {
      id: seriesId,
      userId: 'local',
      title: template.title,
      description: template.description,
      recurrence,
      priority: template.priority,
      keepIfMissed: template.keepIfMissed === true,
      dueRule: deriveDueRuleFromDate(recurrence, baseDate),
      startAt: bounds.start.toISOString(),
      lastGeneratedIntervalStart: bounds.start.toISOString(),
      legacySeriesId: legacyId,
      createdAt: nowIso(),
      updatedAt: nowIso(),
    }

    nextState.taskSeries.push(series)
    nextState.tasks = nextState.tasks.map((t) =>
      t.type === 'recurring' && t.seriesId === legacyId ? { ...t, seriesId } : t,
    )
  }

  return nextState
}

function ensureSeriesInstance(
  state: LocalDatabaseState,
  series: LocalTaskSeries,
  intervalStart: Date,
  now: Date,
): LocalDatabaseState {
  const recurrence = series.recurrence as Recurrence
  const bounds = getIntervalBounds(recurrence, intervalStart)

  if (series.stopAt && bounds.start.getTime() > new Date(series.stopAt).getTime()) {
    return state
  }

  const dueAt = computeDueAtForInterval(recurrence, bounds.start, series.dueRule)
  const status: 'pending' | 'missed' =
    dueAt.getTime() < now.getTime() ? 'missed' : 'pending'
  const missedAt = status === 'missed' ? now.toISOString() : undefined

  const exists = state.tasks.some(
    (t) =>
      t.seriesId === series.id &&
      t.intervalStart === bounds.start.toISOString(),
  )

  if (exists) return state

  const task: Task = {
    id: 't_' + uid(),
    title: series.title,
    description: series.description,
    type: 'recurring',
    recurrence: series.recurrence,
    seriesId: series.id,
    priority: series.priority,
    keepIfMissed: series.keepIfMissed,
    status,
    dueDate: dueAt.toISOString(),
    intervalStart: bounds.start.toISOString(),
    intervalEnd: bounds.end.toISOString(),
    missedAt,
    createdAt: nowIso(),
    updatedAt: nowIso(),
  }

  return { ...state, tasks: [...state.tasks, task] }
}

function syncTaskSeriesForUser(
  state: LocalDatabaseState,
  now: Date,
): LocalDatabaseState {
  let nextState = { ...state, taskSeries: [...state.taskSeries], tasks: [...state.tasks] }

  for (let i = 0; i < nextState.taskSeries.length; i++) {
    const series = { ...nextState.taskSeries[i] }
    const recurrence = series.recurrence as Recurrence
    const currentInterval = getIntervalBounds(recurrence, now)
    const nextInterval = getNextIntervalBounds(recurrence, currentInterval.start)
    const startInterval = getIntervalBounds(recurrence, new Date(series.startAt)).start
    const last = series.lastGeneratedIntervalStart
      ? getIntervalBounds(recurrence, new Date(series.lastGeneratedIntervalStart)).start
      : startInterval

    if (series.keepIfMissed) {
      let cursor = last
      let count = 0
      while (
        cursor.getTime() <= currentInterval.start.getTime() &&
        count < MAX_BACKFILL_INTERVALS
      ) {
        nextState = ensureSeriesInstance(nextState, series, cursor, now)
        cursor = getNextIntervalBounds(recurrence, cursor).start
        count += 1
      }
      nextState = ensureSeriesInstance(nextState, series, nextInterval.start, now)
      series.lastGeneratedIntervalStart = nextInterval.start.toISOString()
      series.updatedAt = nowIso()
    } else {
      nextState = ensureSeriesInstance(nextState, series, currentInterval.start, now)
      nextState = ensureSeriesInstance(nextState, series, nextInterval.start, now)
      series.lastGeneratedIntervalStart = nextInterval.start.toISOString()
      series.updatedAt = nowIso()

      nextState.tasks = nextState.tasks.filter(
        (t) =>
          !(
            t.seriesId === series.id &&
            t.status === 'missed' &&
            t.keepIfMissed === false &&
            t.intervalEnd &&
            new Date(t.intervalEnd).getTime() < now.getTime()
          ),
      )
    }

    nextState.tasks = nextState.tasks.map((t) =>
      t.seriesId === series.id &&
      t.status === 'pending' &&
      t.dueDate &&
      new Date(t.dueDate).getTime() < now.getTime()
        ? { ...t, status: 'missed', missedAt: now.toISOString(), updatedAt: nowIso() }
        : t,
    )

    if (!series.keepIfMissed) {
      nextState.tasks = nextState.tasks.filter(
        (t) =>
          !(
            t.seriesId === series.id &&
            t.status === 'missed' &&
            t.keepIfMissed === false &&
            t.intervalEnd &&
            new Date(t.intervalEnd).getTime() < now.getTime()
          ),
      )
    }

    nextState.taskSeries[i] = series
  }

  return nextState
}

// =========================
// Task Service
// =========================
export const taskService: ITaskService = {
  async getTasks() {
    const now = new Date()

    let state = readState()

    state = {
      ...state,
      tasks: state.tasks.map((t) =>
        t.type === 'one-time' &&
        t.status === 'pending' &&
        t.dueDate &&
        new Date(t.dueDate).getTime() < now.getTime()
          ? { ...t, status: 'missed', missedAt: now.toISOString(), updatedAt: nowIso() }
          : t,
      ),
    }

    state = migrateLegacyRecurringTasks(state)
    state = syncTaskSeriesForUser(state, now)
    writeState(state)

    return sortTasksByDueDate(state.tasks)
  },

  async createTask(input: CreateTaskInput) {
    const now = new Date()
    const { title, description, type, recurrence, priority, dueDate, keepIfMissed, stopAt } =
      input

    if (type === 'recurring' && recurrence) {
      const dueAt = dueDate ? new Date(dueDate) : now
      const bounds = getIntervalBounds(recurrence as Recurrence, dueAt)
      const seriesId = 'ls_' + uid()

      const series: LocalTaskSeries = {
        id: seriesId,
        userId: 'local',
        title,
        description,
        recurrence,
        priority,
        keepIfMissed: keepIfMissed === true,
        dueRule: deriveDueRuleFromDate(recurrence as Recurrence, dueAt),
        startAt: bounds.start.toISOString(),
        stopAt: stopAt ? new Date(stopAt).toISOString() : undefined,
        lastGeneratedIntervalStart: bounds.start.toISOString(),
        createdAt: nowIso(),
        updatedAt: nowIso(),
      }

      let state = readState()
      state = { ...state, taskSeries: [...state.taskSeries, series] }
      state = ensureSeriesInstance(state, series, bounds.start, now)
      writeState(state)

      const created = state.tasks.find(
        (t) => t.seriesId === seriesId && t.intervalStart === bounds.start.toISOString(),
      )
      if (!created) throw new Error('Failed to create task')
      return created
    }

    const task: Task = {
      id: 't_' + uid(),
      title,
      description,
      type,
      priority,
      dueDate: dueDate ? new Date(dueDate).toISOString() : undefined,
      status: 'pending',
      createdAt: nowIso(),
      updatedAt: nowIso(),
    }

    updateState((s) => ({ ...s, tasks: [...s.tasks, task] }))
    return task
  },

  async updateTask(id, updates) {
    const now = new Date()
    let state = readState()

    let updatedTask = state.tasks.find((t) => t.id === id)
    if (!updatedTask) throw new Error('Task not found')

    if (updates.status === 'completed' && updatedTask.seriesId) {
      const series = state.taskSeries.find((s) => s.id === updatedTask!.seriesId)
      if (series) {
        const recurrence = series.recurrence as Recurrence
        const intervalStart = updatedTask.intervalStart
          ? new Date(updatedTask.intervalStart)
          : getIntervalBounds(recurrence, updatedTask.dueDate ? new Date(updatedTask.dueDate) : now).start
        const next = getNextIntervalBounds(recurrence, intervalStart)

        const seriesIdx = state.taskSeries.findIndex((s) => s.id === series.id)
        const updatedSeries = {
          ...series,
          lastGeneratedIntervalStart: next.start.toISOString(),
          updatedAt: nowIso(),
        }
        state = {
          ...state,
          taskSeries: state.taskSeries.map((s, i) => (i === seriesIdx ? updatedSeries : s)),
        }
        state = ensureSeriesInstance(state, updatedSeries, next.start, now)
      }
      updates.completedAt = now.toISOString()
    }

    if (updates.status === 'missed' && !updates.missedAt) {
      updates.missedAt = now.toISOString()
    }

    updatedTask = {
      ...updatedTask,
      ...updates,
      updatedAt: nowIso(),
    } as Task

    state = {
      ...state,
      tasks: state.tasks.map((t) => (t.id === id ? updatedTask! : t)),
    }
    writeState(state)
    return updatedTask
  },

  async deleteTask(id) {
    updateState((s) => ({ ...s, tasks: s.tasks.filter((t) => t.id !== id) }))
  },
}

// =========================
// Finance Service
// =========================
export const financeService: IFinanceService = {
  async getTransactions() {
    return sortTransactionsByDateDesc(readState().transactions)
  },

  async addTransaction(input: CreateTransactionInput) {
    const tx: Transaction = {
      id: 'tx_' + uid(),
      amount: input.amount,
      date: new Date(input.date).toISOString(),
      category: input.category,
      sourceBank: input.sourceBank,
      type: input.type,
      description: input.description,
      createdAt: nowIso(),
      updatedAt: nowIso(),
    }
    updateState((s) => ({ ...s, transactions: [...s.transactions, tx] }))
    return tx
  },

  async deleteTransaction(id) {
    updateState((s) => ({
      ...s,
      transactions: s.transactions.filter((t) => t.id !== id),
    }))
  },

  async uploadStatement(source, data) {
    const now = nowIso()
    const items: Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> = []

    const raw = typeof data === 'string' ? data : JSON.stringify(data)

    if (source === 'bbva') {
      const lines = raw.split('\n')
      for (const line of lines) {
        const [date, desc, amountStr] = line.split(',')
        if (!date || !amountStr) continue
        const amount = parseFloat(amountStr)
        if (!Number.isFinite(amount)) continue
        items.push({
          amount: Math.abs(amount),
          date: new Date(date).toISOString(),
          category: 'Uncategorized',
          sourceBank: 'BBVA',
          type: amount >= 0 ? 'income' : 'expense',
          description: desc?.trim() || undefined,
        })
      }
    } else if (source === 'mercadopago') {
      type MpItem = { date_created?: string; type?: string; transaction_amount?: number; description?: string }
      let parsed: MpItem[] = []
      try {
        const raw: unknown = typeof data === 'string' ? JSON.parse(data) : data
        if (Array.isArray(raw)) parsed = raw as MpItem[]
      } catch {
        parsed = []
      }
      if (Array.isArray(parsed)) {
        for (const item of parsed) {
          if (!item.date_created || item.transaction_amount === undefined) continue
          items.push({
            amount: item.transaction_amount,
            date: new Date(item.date_created).toISOString(),
            category: 'Uncategorized',
            sourceBank: 'MercadoPago',
            type: item.type === 'money_in' ? 'income' : 'expense',
            description: item.description,
          })
        }
      }
    } else if (source === 'nacion') {
      const lines = raw.split('\n')
      for (const line of lines) {
        const [date, desc, amountStr] = line.split(';')
        if (!date || !amountStr) continue
        const amount = parseFloat(amountStr)
        if (!Number.isFinite(amount)) continue
        items.push({
          amount: Math.abs(amount),
          date: new Date(date).toISOString(),
          category: 'Uncategorized',
          sourceBank: 'Banco Nación',
          type: amount >= 0 ? 'income' : 'expense',
          description: desc?.trim() || undefined,
        })
      }
    }

    const created: Transaction[] = items.map((t) => ({
      ...t,
      id: 'tx_' + uid(),
      createdAt: now,
      updatedAt: now,
    }))

    updateState((s) => ({
      ...s,
      transactions: [...s.transactions, ...created],
    }))

    return { imported: created.length, transactions: created }
  },
}

// =========================
// Routine Service
// =========================
export const routineService: IRoutineService = {
  async getRoutines() {
    return readState().routines
  },

  async createRoutine(input: CreateRoutineInput) {
    const routine: Routine = {
      id: 'r_' + uid(),
      title: input.title,
      startTime: input.startTime,
      endTime: input.endTime,
      days: input.days,
      active: input.active ?? true,
      createdAt: nowIso(),
      updatedAt: nowIso(),
    }
    updateState((s) => ({ ...s, routines: [...s.routines, routine] }))
    return routine
  },

  async deleteRoutine(id) {
    updateState((s) => ({ ...s, routines: s.routines.filter((r) => r.id !== id) }))
  },

  async checkIn(routineId, status, date) {
    const now = nowIso()
    const state = readState()
    const existing: RoutineCheckIn | undefined = state.routineCheckIns.find(
      (c) => c.routineId === routineId && c.date === date,
    )

    if (existing) {
      const updated: RoutineCheckIn = {
        ...existing,
        status,
        completedAt: now,
        updatedAt: now,
      }
      updateState((s) => ({
        ...s,
        routineCheckIns: s.routineCheckIns.map((c) =>
          c.id === updated.id ? updated : c,
        ),
      }))
      return updated
    }

    const checkIn: RoutineCheckIn = {
      id: 'ci_' + uid(),
      routineId,
      date,
      status,
      completedAt: now,
      createdAt: now,
      updatedAt: now,
    }
    updateState((s) => ({ ...s, routineCheckIns: [...s.routineCheckIns, checkIn] }))
    return checkIn
  },

  async getCheckIns(options) {
    let items = readState().routineCheckIns
    if (options?.date) items = items.filter((c) => c.date === options.date)
    return items
  },

  async getDisciplineScore(options) {
    const today = new Date().toISOString().split('T')[0]
    const start = options?.startDate || today
    const end = options?.endDate || today

    const state = readState()
    const activeRoutines = state.routines.filter((r) => r.active)
    const completed = state.routineCheckIns.filter(
      (c) => c.status === 'completed' && c.date >= start && c.date <= end,
    )

    if (activeRoutines.length === 0) return { score: 0 }
    const score = (completed.length / activeRoutines.length) * 100
    return { score: Math.min(score, 100) }
  },
}
