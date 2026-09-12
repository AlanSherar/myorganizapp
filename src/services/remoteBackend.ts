import { api } from '../lib/api.js'
import type {
  Task,
  CreateTaskInput,
  Transaction,
  CreateTransactionInput,
  Routine,
  CreateRoutineInput,
  RoutineCheckIn,
  RoutineStatus,
  TaskStatus,
  ITaskService,
  IFinanceService,
  IRoutineService,
} from './types.js'

type RemoteIdLike = { toString(): string } | string

type RemoteTaskDoc = {
  id?: string
  _id?: RemoteIdLike
  title: string
  description?: string
  type: string
  recurrence?: string
  status: TaskStatus
  priority?: string
  dueDate?: unknown
  intervalStart?: unknown
  intervalEnd?: unknown
  seriesId?: string
  series?: RemoteIdLike
  keepIfMissed?: boolean
  completedAt?: unknown
  missedAt?: unknown
  createdAt?: unknown
  updatedAt?: unknown
}

type RemoteTransactionDoc = {
  id?: string
  _id?: RemoteIdLike
  amount: number
  date?: unknown
  category: string
  sourceBank: string
  type: string
  description?: string
  createdAt?: unknown
  updatedAt?: unknown
}

type RemoteRoutineDoc = {
  id?: string
  _id?: RemoteIdLike
  title: string
  startTime?: string
  endTime?: string
  days?: number[]
  active: boolean
  createdAt?: unknown
  updatedAt?: unknown
}

type RemoteRoutineCheckInDoc = {
  id?: string
  _id?: RemoteIdLike
  routineId?: string
  routine?: RemoteIdLike
  date: string
  status: RoutineStatus
  completedAt?: unknown
  createdAt?: unknown
  updatedAt?: unknown
}

function toIso(value: unknown): string | undefined {
  if (value === undefined || value === null) return undefined
  if (value instanceof Date) return value.toISOString()
  if (typeof value === 'string') return value
  if (typeof value === 'number') return new Date(value).toISOString()
  try {
    return new Date(String(value)).toISOString()
  } catch {
    return undefined
  }
}

function mapTask(doc: RemoteTaskDoc): Task {
  return {
    id: doc.id ?? doc._id?.toString?.() ?? String(doc._id ?? ''),
    title: doc.title,
    description: doc.description,
    type: doc.type as Task['type'],
    recurrence: doc.recurrence as Task['recurrence'],
    status: doc.status,
    priority: doc.priority as Task['priority'],
    dueDate: toIso(doc.dueDate),
    intervalStart: toIso(doc.intervalStart),
    intervalEnd: toIso(doc.intervalEnd),
    seriesId: doc.seriesId ?? doc.series?.toString?.() ?? (doc.series as string | undefined),
    keepIfMissed: doc.keepIfMissed,
    completedAt: toIso(doc.completedAt),
    missedAt: toIso(doc.missedAt),
    createdAt: toIso(doc.createdAt) ?? new Date().toISOString(),
    updatedAt: toIso(doc.updatedAt) ?? new Date().toISOString(),
  }
}

function mapTransaction(doc: RemoteTransactionDoc): Transaction {
  return {
    id: doc.id ?? doc._id?.toString?.() ?? String(doc._id ?? ''),
    amount: doc.amount,
    date: toIso(doc.date) ?? new Date().toISOString(),
    category: doc.category,
    sourceBank: doc.sourceBank,
    type: doc.type as Transaction['type'],
    description: doc.description,
    createdAt: toIso(doc.createdAt) ?? new Date().toISOString(),
    updatedAt: toIso(doc.updatedAt) ?? new Date().toISOString(),
  }
}

function mapRoutine(doc: RemoteRoutineDoc): Routine {
  return {
    id: doc.id ?? doc._id?.toString?.() ?? String(doc._id ?? ''),
    title: doc.title,
    startTime: doc.startTime,
    endTime: doc.endTime,
    days: doc.days ?? [],
    active: doc.active,
    createdAt: toIso(doc.createdAt) ?? new Date().toISOString(),
    updatedAt: toIso(doc.updatedAt) ?? new Date().toISOString(),
  }
}

function mapRoutineCheckIn(doc: RemoteRoutineCheckInDoc): RoutineCheckIn {
  return {
    id: doc.id ?? doc._id?.toString?.() ?? String(doc._id ?? ''),
    routineId: doc.routineId ?? doc.routine?.toString?.() ?? (doc.routine as string | undefined),
    date: doc.date,
    status: doc.status,
    completedAt: toIso(doc.completedAt),
    createdAt: toIso(doc.createdAt) ?? new Date().toISOString(),
    updatedAt: toIso(doc.updatedAt) ?? new Date().toISOString(),
  }
}

export const taskService: ITaskService = {
  async getTasks() {
    const docs = await api.get('/tasks')
    return Array.isArray(docs) ? docs.map(mapTask) : []
  },

  async createTask(input: CreateTaskInput) {
    const doc = await api.post('/tasks', input)
    return mapTask(doc)
  },

  async updateTask(id: string, updates: Partial<Task> & { status?: TaskStatus }) {
    const doc = await api.put(`/tasks/${id}`, updates)
    return mapTask(doc)
  },

  async deleteTask(id: string) {
    await api.delete(`/tasks/${id}`)
  },
}

export const financeService: IFinanceService = {
  async getTransactions() {
    const docs = await api.get('/finance')
    return Array.isArray(docs) ? docs.map(mapTransaction) : []
  },

  async addTransaction(input: CreateTransactionInput) {
    const doc = await api.post('/finance', input)
    return mapTransaction(doc)
  },

  async deleteTransaction(id: string) {
    await api.delete(`/finance/${id}`)
  },

  async uploadStatement(source: string, data: unknown) {
    const result = await api.post('/finance/upload', { source, data })
    return {
      imported: result.transactions?.length ?? 0,
      transactions: Array.isArray(result.transactions)
        ? result.transactions.map(mapTransaction)
        : [],
    }
  },
}

export const routineService: IRoutineService = {
  async getRoutines() {
    const docs = await api.get('/routines')
    return Array.isArray(docs) ? docs.map(mapRoutine) : []
  },

  async createRoutine(input: CreateRoutineInput) {
    const doc = await api.post('/routines', input)
    return mapRoutine(doc)
  },

  async deleteRoutine(id: string) {
    await api.delete(`/routines/${id}`)
  },

  async checkIn(routineId: string, status: RoutineStatus, date: string) {
    const doc = await api.post('/routines/checkin', { routineId, status, date })
    return mapRoutineCheckIn(doc)
  },

  async getCheckIns(options?: { date?: string }) {
    const params = new URLSearchParams()
    if (options?.date) params.set('date', options.date)
    const qs = params.toString()
    const docs = await api.get(`/routines/checkins${qs ? `?${qs}` : ''}`)
    return Array.isArray(docs) ? docs.map(mapRoutineCheckIn) : []
  },

  async getDisciplineScore(options?: { startDate?: string; endDate?: string }) {
    const params = new URLSearchParams()
    if (options?.startDate) params.set('startDate', options.startDate)
    if (options?.endDate) params.set('endDate', options.endDate)
    const qs = params.toString()
    const result = await api.get(`/routines/score${qs ? `?${qs}` : ''}`)
    return { score: Number(result?.score ?? 0) }
  },
}
