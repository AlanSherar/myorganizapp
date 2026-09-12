import { createClient, type SupabaseClient } from '@supabase/supabase-js'
import type {
  ITaskSeriesRepository,
  TaskSeries,
  TaskPriority,
  TaskRecurrence,
  TaskSeriesDueRule,
} from '../types.js'

type SbTaskSeriesRow = {
  id: string
  user_id: string
  title: string
  description: string | null
  recurrence: TaskRecurrence
  priority: TaskPriority
  keep_if_missed: boolean
  due_rule: TaskSeriesDueRule
  start_at: string
  stop_at: string | null
  last_generated_interval_start: string | null
  legacy_series_id: string | null
  created_at: string
  updated_at: string
}

function toDomain(row: SbTaskSeriesRow): TaskSeries {
  return {
    id: row.id,
    userId: row.user_id,
    title: row.title,
    description: row.description ?? undefined,
    recurrence: row.recurrence,
    priority: row.priority,
    keepIfMissed: row.keep_if_missed,
    dueRule: row.due_rule,
    startAt: new Date(row.start_at),
    stopAt: row.stop_at ? new Date(row.stop_at) : undefined,
    lastGeneratedIntervalStart: row.last_generated_interval_start
      ? new Date(row.last_generated_interval_start)
      : undefined,
    legacySeriesId: row.legacy_series_id ?? undefined,
    createdAt: new Date(row.created_at),
    updatedAt: new Date(row.updated_at),
  }
}

function toSupabase(
  data: Partial<TaskSeries>,
  userId?: string,
): Partial<SbTaskSeriesRow> {
  const out: Partial<SbTaskSeriesRow> = {}
  if (userId) out.user_id = userId
  if (data.userId !== undefined) out.user_id = data.userId
  if (data.title !== undefined) out.title = data.title
  if (data.description !== undefined) out.description = data.description ?? null
  if (data.recurrence !== undefined) out.recurrence = data.recurrence
  if (data.priority !== undefined) out.priority = data.priority
  if (data.keepIfMissed !== undefined) out.keep_if_missed = data.keepIfMissed
  if (data.dueRule !== undefined) out.due_rule = data.dueRule
  if (data.startAt !== undefined) out.start_at = new Date(data.startAt).toISOString()
  if (data.stopAt !== undefined) {
    out.stop_at = data.stopAt ? new Date(data.stopAt).toISOString() : null
  }
  if (data.lastGeneratedIntervalStart !== undefined) {
    out.last_generated_interval_start = data.lastGeneratedIntervalStart
      ? new Date(data.lastGeneratedIntervalStart).toISOString()
      : null
  }
  if (data.legacySeriesId !== undefined) {
    out.legacy_series_id = data.legacySeriesId ?? null
  }
  return out
}

function throwIfError(result: { error: unknown } | null) {
  if (result?.error) {
    throw new Error('[Supabase TaskSeriesRepository] ' + String((result.error as Error)?.message ?? result.error))
  }
}

export function createTaskSeriesRepository(client?: SupabaseClient): ITaskSeriesRepository {
  const sb =
    client ??
    createClient(process.env.SUPABASE_URL ?? '', process.env.SUPABASE_SERVICE_ROLE_KEY ?? '', {
      auth: { persistSession: false, autoRefreshToken: false },
    })

  const table = () => sb.from('task_series')

  return {
    async findByUserId(userId) {
      const { data, error } = await table().select('*').eq('user_id', userId)
      throwIfError({ error })
      return (data as SbTaskSeriesRow[] | null ?? []).map(toDomain)
    },

    async findById(id) {
      const { data, error } = await table().select('*').eq('id', id).maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbTaskSeriesRow) : null
    },

    async findOneByFilter(filter) {
      let q = table().select('*').eq('user_id', filter.userId)
      if (filter.legacySeriesId !== undefined) q = q.eq('legacy_series_id', filter.legacySeriesId)
      const { data, error } = await q.maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbTaskSeriesRow) : null
    },

    async exists(filter) {
      let q = table().select('id', { count: 'exact', head: true }).eq('user_id', filter.userId)
      if (filter.legacySeriesId !== undefined) q = q.eq('legacy_series_id', filter.legacySeriesId)
      const { count, error } = await q
      throwIfError({ error })
      return Number(count ?? 0) > 0
    },

    async create(series) {
      const payload = toSupabase(series, series.userId) as SbTaskSeriesRow
      const { data, error } = await table().insert([payload]).select().single()
      throwIfError({ error })
      return toDomain(data as SbTaskSeriesRow)
    },

    async update(id, updates) {
      const payload = toSupabase(updates) as Partial<SbTaskSeriesRow>
      const { data, error } = await table().update(payload).eq('id', id).select().maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbTaskSeriesRow) : null
    },

    async save(series) {
      const payload = toSupabase(series, series.userId) as SbTaskSeriesRow
      const { data, error } = await table()
        .update(payload)
        .eq('id', series.id)
        .select()
        .maybeSingle()
      throwIfError({ error })
      if (!data) throw new Error('TaskSeries not found for save: ' + series.id)
      return toDomain(data as SbTaskSeriesRow)
    },
  }
}
