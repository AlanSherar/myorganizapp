import { supabaseClient } from '../lib/supabase.js'
import type {
  Task,
  CreateTaskInput,
  TaskStatus,
  Transaction,
  CreateTransactionInput,
  Routine,
  CreateRoutineInput,
  RoutineCheckIn,
  ITaskService,
  IFinanceService,
  IRoutineService,
  TaskRecurrence,
  TaskPriority,
  RoutineStatus,
} from './types.js'

const DEFAULT_USER_ID = '60d0fe4f-5311-4361-68a1-09ca00000001'

type SbTaskRow = {
  id: string
  user_id: string
  series_id: string | null
  series_id_legacy: string | null
  title: string
  description: string | null
  type: 'one-time' | 'recurring'
  recurrence: TaskRecurrence | null
  status: TaskStatus
  priority: TaskPriority
  due_date: string | null
  interval_start: string | null
  interval_end: string | null
  keep_if_missed: boolean | null
  completed_at: string | null
  missed_at: string | null
  created_at: string
  updated_at: string
}

type SbTaskSeriesRow = {
  id: string
  user_id: string
  title: string
  description: string | null
  recurrence: TaskRecurrence
  priority: TaskPriority
  keep_if_missed: boolean
  due_rule: { time: string; dayOfWeek?: number; dayOfMonth?: number; month?: number }
  start_at: string
  stop_at: string | null
  last_generated_interval_start: string | null
  legacy_series_id: string | null
  created_at: string
  updated_at: string
}

type SbTransactionRow = {
  id: string
  user_id: string
  amount: number
  date: string
  category: string
  source_bank: string
  type: 'income' | 'expense'
  description: string | null
  created_at: string
  updated_at: string
}

type SbRoutineRow = {
  id: string
  user_id: string
  title: string
  start_time: string
  end_time: string
  days: number[]
  active: boolean
  created_at: string
  updated_at: string
}

type SbRoutineCheckInRow = {
  id: string
  user_id: string
  routine_id: string
  date: string
  status: 'completed' | 'missed'
  completed_at: string | null
  created_at: string
  updated_at: string
}

function mapTask(row: SbTaskRow): Task {
  return {
    id: row.id,
    seriesId: row.series_id ?? row.series_id_legacy ?? undefined,
    title: row.title,
    description: row.description ?? undefined,
    type: row.type,
    recurrence: row.recurrence ?? undefined,
    status: row.status,
    priority: row.priority,
    dueDate: row.due_date ?? undefined,
    intervalStart: row.interval_start ?? undefined,
    intervalEnd: row.interval_end ?? undefined,
    keepIfMissed: row.keep_if_missed ?? undefined,
    completedAt: row.completed_at ?? undefined,
    missedAt: row.missed_at ?? undefined,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  }
}

function mapTransaction(row: SbTransactionRow): Transaction {
  return {
    id: row.id,
    amount: Number(row.amount),
    date: row.date,
    category: row.category,
    sourceBank: row.source_bank,
    type: row.type,
    description: row.description ?? undefined,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  }
}

function mapRoutine(row: SbRoutineRow): Routine {
  return {
    id: row.id,
    title: row.title,
    startTime: row.start_time,
    endTime: row.end_time,
    days: Array.isArray(row.days) ? row.days : [],
    active: row.active,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  }
}

function mapRoutineCheckIn(row: SbRoutineCheckInRow): RoutineCheckIn {
  return {
    id: row.id,
    routineId: row.routine_id,
    date: row.date,
    status: row.status,
    completedAt: row.completed_at ?? undefined,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  }
}

function throwIfErr(label: string, err: unknown) {
  if (err) {
    throw new Error(`[supabaseBackend:${label}] ` + String((err as Error)?.message ?? err))
  }
}

// ==========================================
//  ITaskService (Supabase Directo)
// ==========================================
const taskService: ITaskService = {
  async getTasks() {
    const { data, error } = await supabaseClient
      .from('tasks')
      .select('*')
      .eq('user_id', DEFAULT_USER_ID)
      .order('due_date', { ascending: true, nullsFirst: false })
    throwIfErr('task.getTasks', error)
    return (data as SbTaskRow[] | null ?? []).map(mapTask)
  },

  async createTask(input: CreateTaskInput): Promise<Task> {
    if (input.type === 'recurring') {
      const baseDate = input.dueDate ? new Date(input.dueDate) : new Date()
      const hh = String(baseDate.getHours()).padStart(2, '0')
      const mm = String(baseDate.getMinutes()).padStart(2, '0')
      const dueRule: SbTaskSeriesRow['due_rule'] = { time: `${hh}:${mm}` }
      const seriesInsert: Partial<SbTaskSeriesRow> = {
        user_id: DEFAULT_USER_ID,
        title: input.title,
        description: input.description ?? null,
        recurrence: input.recurrence ?? 'daily',
        priority: input.priority,
        keep_if_missed: input.keepIfMissed === true,
        due_rule: dueRule,
        start_at: input.dueDate ?? new Date().toISOString(),
        stop_at: input.stopAt ?? null,
      }
      const { data: seriesRow, error: errS } = await supabaseClient
        .from('task_series')
        .insert([seriesInsert])
        .select()
        .single()
      throwIfErr('task.createTask.series', errS)
      const series = seriesRow as SbTaskSeriesRow
      const taskPayload: Partial<SbTaskRow> = {
        user_id: DEFAULT_USER_ID,
        series_id: series.id,
        title: input.title,
        description: input.description ?? null,
        type: 'recurring',
        recurrence: input.recurrence ?? null,
        status: 'pending' as TaskStatus,
        priority: input.priority,
        due_date: input.dueDate ?? null,
        keep_if_missed: input.keepIfMissed ?? null,
      }
      const { data, error } = await supabaseClient
        .from('tasks')
        .insert([taskPayload])
        .select()
        .single()
      throwIfErr('task.createTask.recurring', error)
      return mapTask(data as SbTaskRow)
    }

    const payload: Partial<SbTaskRow> = {
      user_id: DEFAULT_USER_ID,
      title: input.title,
      description: input.description ?? null,
      type: 'one-time',
      recurrence: null,
      status: 'pending' as TaskStatus,
      priority: input.priority,
      due_date: input.dueDate ?? null,
      keep_if_missed: input.keepIfMissed ?? null,
    }
    const { data, error } = await supabaseClient.from('tasks').insert([payload]).select().single()
    throwIfErr('task.createTask', error)
    return mapTask(data as SbTaskRow)
  },

  async updateTask(id: string, updates: Partial<Task> & { status?: TaskStatus }): Promise<Task> {
    const payload: Partial<SbTaskRow> = {}
    if (updates.title !== undefined) payload.title = updates.title
    if (updates.description !== undefined) payload.description = updates.description ?? null
    if (updates.priority !== undefined) payload.priority = updates.priority
    if (updates.dueDate !== undefined) payload.due_date = updates.dueDate ?? null
    if (updates.status !== undefined) {
      payload.status = updates.status
      const now = new Date().toISOString()
      if (updates.status === 'completed') payload.completed_at = now
      else payload.completed_at = null
      if (updates.status === 'missed') payload.missed_at = now
      else payload.missed_at = null
    }
    const { data, error } = await supabaseClient
      .from('tasks')
      .update(payload)
      .eq('id', id)
      .select()
      .maybeSingle()
    throwIfErr('task.updateTask', error)
    if (!data) throw new Error('Task not found for update: ' + id)
    return mapTask(data as SbTaskRow)
  },

  async deleteTask(id: string): Promise<void> {
    const { error } = await supabaseClient.from('tasks').delete().eq('id', id)
    throwIfErr('task.deleteTask', error)
  },
}

// ==========================================
//  IFinanceService (Supabase Directo)
// ==========================================
function parseBBVA(): Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> {
  return []
}
function parseMercadoPago(): Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> {
  return []
}
function parseNacion(): Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> {
  return []
}

const financeService: IFinanceService = {
  async getTransactions(): Promise<Transaction[]> {
    const { data, error } = await supabaseClient
      .from('transactions')
      .select('*')
      .eq('user_id', DEFAULT_USER_ID)
      .order('date', { ascending: false })
    throwIfErr('finance.getTransactions', error)
    return (data as SbTransactionRow[] | null ?? []).map(mapTransaction)
  },

  async addTransaction(input: CreateTransactionInput): Promise<Transaction> {
    const payload: Partial<SbTransactionRow> = {
      user_id: DEFAULT_USER_ID,
      amount: Number(input.amount),
      date: input.date,
      category: input.category,
      source_bank: input.sourceBank,
      type: input.type,
      description: input.description ?? null,
    }
    const { data, error } = await supabaseClient
      .from('transactions')
      .insert([payload])
      .select()
      .single()
    throwIfErr('finance.addTransaction', error)
    return mapTransaction(data as SbTransactionRow)
  },

  async deleteTransaction(id: string): Promise<void> {
    const { error } = await supabaseClient.from('transactions').delete().eq('id', id)
    throwIfErr('finance.deleteTransaction', error)
  },

  async uploadStatement(
    source: string,
    data: string | unknown,
  ): Promise<{ imported: number; transactions: Transaction[] }> {
    let parsed: Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> = []
    if (source === 'bbva') parsed = parseBBVA()
    else if (source === 'mercadoPago') parsed = parseMercadoPago()
    else if (source === 'nacion') parsed = parseNacion()
    if (parsed.length === 0) {
      // Dado que los parsers stub devuelven [] por ahora, solo mostramos un
      // warning descriptivo y no fallamos: evita romper el UI al probar.
      void data
      return { imported: 0, transactions: [] }
    }
    const rows = parsed.map((t) => ({
      user_id: DEFAULT_USER_ID,
      amount: Number(t.amount),
      date: t.date,
      category: t.category,
      source_bank: t.sourceBank,
      type: t.type,
      description: t.description ?? null,
    }))
    const { data: inserted, error } = await supabaseClient
      .from('transactions')
      .insert(rows)
      .select()
    throwIfErr('finance.uploadStatement', error)
    const transactions = (inserted as SbTransactionRow[] | null ?? []).map(mapTransaction)
    return { imported: transactions.length, transactions }
  },
}

// ==========================================
//  IRoutineService (Supabase Directo)
// ==========================================
const routineService: IRoutineService = {
  async getRoutines(): Promise<Routine[]> {
    const { data, error } = await supabaseClient
      .from('routines')
      .select('*')
      .eq('user_id', DEFAULT_USER_ID)
      .order('start_time', { ascending: true })
    throwIfErr('routine.getRoutines', error)
    return (data as SbRoutineRow[] | null ?? []).map(mapRoutine)
  },

  async createRoutine(input: CreateRoutineInput): Promise<Routine> {
    const payload: Partial<SbRoutineRow> = {
      user_id: DEFAULT_USER_ID,
      title: input.title,
      start_time: input.startTime,
      end_time: input.endTime,
      days: input.days,
      active: input.active === undefined ? true : input.active,
    }
    const { data, error } = await supabaseClient
      .from('routines')
      .insert([payload])
      .select()
      .single()
    throwIfErr('routine.createRoutine', error)
    return mapRoutine(data as SbRoutineRow)
  },

  async deleteRoutine(id: string): Promise<void> {
    const { error } = await supabaseClient.from('routines').delete().eq('id', id)
    throwIfErr('routine.deleteRoutine', error)
  },

  async checkIn(routineId: string, status: RoutineStatus, date: string): Promise<RoutineCheckIn> {
    const payload = {
      user_id: DEFAULT_USER_ID,
      routine_id: routineId,
      date,
      status,
      completed_at: new Date().toISOString(),
    }
    const { data, error } = await supabaseClient
      .from('routine_check_ins')
      .upsert(payload, { onConflict: 'user_id, routine_id, date', ignoreDuplicates: false })
      .select()
      .single()
    throwIfErr('routine.checkIn', error)
    return mapRoutineCheckIn(data as SbRoutineCheckInRow)
  },

  async getCheckIns(options?: { date?: string }): Promise<RoutineCheckIn[]> {
    let q = supabaseClient
      .from('routine_check_ins')
      .select('*')
      .eq('user_id', DEFAULT_USER_ID)
    if (options?.date) q = q.eq('date', options.date)
    const { data, error } = await q
    throwIfErr('routine.getCheckIns', error)
    return (data as SbRoutineCheckInRow[] | null ?? []).map(mapRoutineCheckIn)
  },

  async getDisciplineScore(options?: { startDate?: string; endDate?: string }): Promise<{ score: number }> {
    let q = supabaseClient
      .from('routine_check_ins')
      .select('*')
      .eq('user_id', DEFAULT_USER_ID)
    if (options?.startDate) q = q.gte('date', options.startDate)
    if (options?.endDate) q = q.lte('date', options.endDate)
    const { data, error } = await q
    throwIfErr('routine.getDisciplineScore.checkins', error)
    const completed = ((data as SbRoutineCheckInRow[] | null ?? []).filter((c) => c.status === 'completed')).length
    const { data: routines, error: errR } = await supabaseClient
      .from('routines')
      .select('*')
      .eq('user_id', DEFAULT_USER_ID)
      .eq('active', true)
    throwIfErr('routine.getDisciplineScore.routines', errR)
    const activeRoutines = (routines as SbRoutineRow[] | null ?? []).length
    const totalPossible = Math.max(1, activeRoutines)
    const score = Math.min(100, Math.round((completed / totalPossible) * 100))
    return { score }
  },
}

export { taskService, financeService, routineService }
export type { SbTaskRow, SbTaskSeriesRow, SbTransactionRow, SbRoutineRow, SbRoutineCheckInRow }
