import { createClient, type SupabaseClient } from '@supabase/supabase-js'
import type {
  ITaskRepository,
  Task,
  TaskStatus,
  TaskType,
  TaskRecurrence,
  TaskPriority,
} from '../types.js'

type SbTaskRow = {
  id: string
  user_id: string
  series_id: string | null
  series_id_legacy: string | null
  title: string
  description: string | null
  type: TaskType
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

function toDomain(row: SbTaskRow): Task {
  return {
    id: row.id,
    userId: row.user_id,
    seriesId: row.series_id ?? row.series_id_legacy ?? undefined,
    title: row.title,
    description: row.description ?? undefined,
    type: row.type,
    recurrence: row.recurrence ?? undefined,
    status: row.status,
    priority: row.priority,
    dueDate: row.due_date ? new Date(row.due_date) : undefined,
    intervalStart: row.interval_start ? new Date(row.interval_start) : undefined,
    intervalEnd: row.interval_end ? new Date(row.interval_end) : undefined,
    keepIfMissed: row.keep_if_missed ?? undefined,
    completedAt: row.completed_at ? new Date(row.completed_at) : undefined,
    missedAt: row.missed_at ? new Date(row.missed_at) : undefined,
    createdAt: new Date(row.created_at),
    updatedAt: new Date(row.updated_at),
  }
}

function toSupabase(
  data: Partial<Task>,
  userId?: string,
): Partial<SbTaskRow> {
  const out: Partial<SbTaskRow> = {}
  if (userId) out.user_id = userId
  if (data.userId !== undefined) out.user_id = data.userId
  if (data.seriesId !== undefined) {
    if (data.seriesId && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(data.seriesId)) {
      out.series_id = data.seriesId
      out.series_id_legacy = null
    } else if (data.seriesId) {
      out.series_id = null
      out.series_id_legacy = data.seriesId
    } else {
      out.series_id = null
      out.series_id_legacy = null
    }
  }
  if (data.title !== undefined) out.title = data.title
  if (data.description !== undefined) out.description = data.description ?? null
  if (data.type !== undefined) out.type = data.type
  if (data.recurrence !== undefined) out.recurrence = data.recurrence ?? null
  if (data.status !== undefined) out.status = data.status
  if (data.priority !== undefined) out.priority = data.priority
  if (data.dueDate !== undefined) out.due_date = data.dueDate ? new Date(data.dueDate).toISOString() : null
  if (data.intervalStart !== undefined) {
    out.interval_start = data.intervalStart ? new Date(data.intervalStart).toISOString() : null
  }
  if (data.intervalEnd !== undefined) {
    out.interval_end = data.intervalEnd ? new Date(data.intervalEnd).toISOString() : null
  }
  if (data.keepIfMissed !== undefined) out.keep_if_missed = data.keepIfMissed ?? null
  if (data.completedAt !== undefined) {
    out.completed_at = data.completedAt ? new Date(data.completedAt).toISOString() : null
  }
  if (data.missedAt !== undefined) {
    out.missed_at = data.missedAt ? new Date(data.missedAt).toISOString() : null
  }
  return out
}

function throwIfError(result: { error: unknown } | null) {
  if (result?.error) {
    throw new Error('[Supabase TaskRepository] ' + String((result.error as Error)?.message ?? result.error))
  }
}

export function createTaskRepository(client?: SupabaseClient): ITaskRepository {
  const sb =
    client ??
    createClient(process.env.SUPABASE_URL ?? '', process.env.SUPABASE_SERVICE_ROLE_KEY ?? '', {
      auth: { persistSession: false, autoRefreshToken: false },
    })

  const table = () => sb.from('tasks')

  return {
    async findByUserId(userId) {
      const { data, error } = await table().select('*').eq('user_id', userId).order('due_date', { ascending: true, nullsFirst: false })
      throwIfError({ error })
      return (data as SbTaskRow[] | null ?? []).map(toDomain)
    },

    async findById(id) {
      const { data, error } = await table().select('*').eq('id', id).maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbTaskRow) : null
    },

    async create(task) {
      const payload = toSupabase(task, task.userId) as SbTaskRow
      const { data, error } = await table().insert([payload]).select().single()
      throwIfError({ error })
      return toDomain(data as SbTaskRow)
    },

    async createMany(tasks) {
      if (tasks.length === 0) return []
      const payload = tasks.map((t) => toSupabase(t, t.userId)) as SbTaskRow[]
      const { data, error } = await table().insert(payload).select()
      throwIfError({ error })
      return (data as SbTaskRow[] | null ?? []).map(toDomain)
    },

    async update(id, updates) {
      const payload = toSupabase(updates) as Partial<SbTaskRow>
      const setData = { ...payload } as Record<string, unknown>

      if (updates.status === 'completed' && !setData.completed_at) {
        setData.completed_at = new Date().toISOString()
      } else if (updates.status && updates.status !== 'completed') {
        setData.completed_at = null
      }
      if (updates.status === 'missed' && !setData.missed_at) {
        setData.missed_at = new Date().toISOString()
      } else if (updates.status && updates.status !== 'missed') {
        setData.missed_at = null
      }

      const { data, error } = await table()
        .update(setData)
        .eq('id', id)
        .select()
        .maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbTaskRow) : null
    },

    async updateManyByFilter(filter, updates) {
      const payload = toSupabase(updates, filter.userId) as Partial<SbTaskRow>

      if (updates.status === 'completed' && !payload.completed_at) {
        payload.completed_at = new Date().toISOString()
      } else if (updates.status && updates.status !== 'completed') {
        payload.completed_at = null
      }
      if (updates.status === 'missed' && !payload.missed_at) {
        payload.missed_at = new Date().toISOString()
      } else if (updates.status && updates.status !== 'missed') {
        payload.missed_at = null
      }

      let q = table().update(payload).eq('user_id', filter.userId)
      if (filter.type) q = q.eq('type', filter.type)
      if (filter.status) q = q.eq('status', filter.status)
      if (filter.dueDateBefore) q = q.lt('due_date', new Date(filter.dueDateBefore).toISOString())
      if (filter.seriesId) q = q.or(`series_id.eq.${filter.seriesId},series_id_legacy.eq.${filter.seriesId}`)
      if (filter.keepIfMissed !== undefined) q = q.eq('keep_if_missed', filter.keepIfMissed)
      if (filter.intervalEndBefore) q = q.lt('interval_end', new Date(filter.intervalEndBefore).toISOString())

      const { error } = await q
      throwIfError({ error })

      const c = table().select('id', { count: 'exact', head: true }).eq('user_id', filter.userId)
      let cc = c
      if (filter.type) cc = cc.eq('type', filter.type)
      if (filter.status) cc = cc.eq('status', filter.status)
      if (filter.dueDateBefore) cc = cc.lt('due_date', new Date(filter.dueDateBefore).toISOString())
      if (filter.seriesId) cc = cc.or(`series_id.eq.${filter.seriesId},series_id_legacy.eq.${filter.seriesId}`)
      if (filter.keepIfMissed !== undefined) cc = cc.eq('keep_if_missed', filter.keepIfMissed)
      if (filter.intervalEndBefore) cc = cc.lt('interval_end', new Date(filter.intervalEndBefore).toISOString())
      const { count: after } = await cc
      return Number(after ?? 0)
    },

    async delete(id) {
      const { error } = await table().delete().eq('id', id)
      throwIfError({ error })
    },

    async deleteManyByFilter(filter) {
      let q = table().delete().eq('user_id', filter.userId)
      if (filter.seriesId) q = q.or(`series_id.eq.${filter.seriesId},series_id_legacy.eq.${filter.seriesId}`)
      if (filter.status) q = q.eq('status', filter.status)
      if (filter.keepIfMissed !== undefined) q = q.eq('keep_if_missed', filter.keepIfMissed)
      if (filter.intervalEndBefore) q = q.lt('interval_end', new Date(filter.intervalEndBefore).toISOString())
      const { error } = await q
      throwIfError({ error })
      return 0
    },

    async findOneByFilter(filter) {
      let q = table().select('*').eq('user_id', filter.userId).limit(1)
      if (filter.seriesId) q = q.or(`series_id.eq.${filter.seriesId},series_id_legacy.eq.${filter.seriesId}`)
      if (filter.intervalStart) q = q.gte('interval_start', new Date(filter.intervalStart).toISOString())
      const { data, error } = await q.maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbTaskRow) : null
    },

    async distinctSeriesIdsByFilter(filter) {
      let q = table().select('series_id_legacy').eq('user_id', filter.userId).eq('type', filter.type)
      if (filter.seriesExists === false) q = q.is('series_id', null)
      if (filter.seriesIdExists === true) q = q.not('series_id_legacy', 'is', null)
      const { data, error } = await q
      throwIfError({ error })
      const rows = data as Array<{ series_id_legacy: string | null }> | null ?? []
      return rows.map((r) => r.series_id_legacy).filter((v): v is string => typeof v === 'string')
    },
  }
}
