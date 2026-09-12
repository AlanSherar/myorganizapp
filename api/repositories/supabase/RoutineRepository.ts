import { createClient, type SupabaseClient } from '@supabase/supabase-js'
import type { IRoutineRepository, Routine } from '../types.js'

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

function toDomain(row: SbRoutineRow): Routine {
  return {
    id: row.id,
    userId: row.user_id,
    title: row.title,
    startTime: row.start_time,
    endTime: row.end_time,
    days: Array.isArray(row.days) ? row.days : [],
    active: row.active,
    createdAt: new Date(row.created_at),
    updatedAt: new Date(row.updated_at),
  }
}

function toSupabase(data: Partial<Routine>, userId?: string): Partial<SbRoutineRow> {
  const out: Partial<SbRoutineRow> = {}
  if (userId) out.user_id = userId
  if (data.userId !== undefined) out.user_id = data.userId
  if (data.title !== undefined) out.title = data.title
  if (data.startTime !== undefined) out.start_time = data.startTime
  if (data.endTime !== undefined) out.end_time = data.endTime
  if (data.days !== undefined) out.days = data.days
  if (data.active !== undefined) out.active = data.active
  return out
}

function throwIfError(result: { error: unknown } | null) {
  if (result?.error) {
    throw new Error('[Supabase RoutineRepository] ' + String((result.error as Error)?.message ?? result.error))
  }
}

export function createRoutineRepository(client?: SupabaseClient): IRoutineRepository {
  const sb =
    client ??
    createClient(process.env.SUPABASE_URL ?? '', process.env.SUPABASE_SERVICE_ROLE_KEY ?? '', {
      auth: { persistSession: false, autoRefreshToken: false },
    })

  const table = () => sb.from('routines')

  return {
    async findByUserId(userId) {
      const { data, error } = await table().select('*').eq('user_id', userId)
      throwIfError({ error })
      return (data as SbRoutineRow[] | null ?? []).map(toDomain)
    },

    async findByUserIdAndActive(userId, active) {
      const { data, error } = await table().select('*').eq('user_id', userId).eq('active', active)
      throwIfError({ error })
      return (data as SbRoutineRow[] | null ?? []).map(toDomain)
    },

    async findById(id) {
      const { data, error } = await table().select('*').eq('id', id).maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbRoutineRow) : null
    },

    async create(routine) {
      const payload = toSupabase(routine, routine.userId) as SbRoutineRow
      const { data, error } = await table().insert([payload]).select().single()
      throwIfError({ error })
      return toDomain(data as SbRoutineRow)
    },

    async delete(id) {
      const { error } = await table().delete().eq('id', id)
      throwIfError({ error })
    },
  }
}
