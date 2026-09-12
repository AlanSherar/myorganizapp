import { createClient, type SupabaseClient } from '@supabase/supabase-js'
import type {
  IRoutineCheckInRepository,
  RoutineCheckIn,
  RoutineStatus,
} from '../types.js'

type SbRoutineCheckInRow = {
  id: string
  user_id: string
  routine_id: string
  date: string
  status: RoutineStatus
  completed_at: string | null
  created_at: string
  updated_at: string
}

function toDomain(row: SbRoutineCheckInRow): RoutineCheckIn {
  return {
    id: row.id,
    userId: row.user_id,
    routineId: row.routine_id,
    date: row.date,
    status: row.status,
    completedAt: row.completed_at ? new Date(row.completed_at) : undefined,
    createdAt: new Date(row.created_at),
    updatedAt: new Date(row.updated_at),
  }
}

function throwIfError(result: { error: unknown } | null) {
  if (result?.error) {
    throw new Error('[Supabase RoutineCheckInRepository] ' + String((result.error as Error)?.message ?? result.error))
  }
}

export function createRoutineCheckInRepository(client?: SupabaseClient): IRoutineCheckInRepository {
  const sb =
    client ??
    createClient(process.env.SUPABASE_URL ?? '', process.env.SUPABASE_SERVICE_ROLE_KEY ?? '', {
      auth: { persistSession: false, autoRefreshToken: false },
    })

  const table = () => sb.from('routine_check_ins')

  return {
    async findByFilter(filter) {
      let q = table().select('*').eq('user_id', filter.userId)
      if (filter.date) q = q.eq('date', filter.date)
      if (filter.status) q = q.eq('status', filter.status)
      if (filter.dateGte) q = q.gte('date', filter.dateGte)
      if (filter.dateLte) q = q.lte('date', filter.dateLte)
      const { data, error } = await q
      throwIfError({ error })
      return (data as SbRoutineCheckInRow[] | null ?? []).map(toDomain)
    },

    async findOneByFilter(filter) {
      const { data, error } = await table()
        .select('*')
        .eq('user_id', filter.userId)
        .eq('routine_id', filter.routineId)
        .eq('date', filter.date)
        .maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbRoutineCheckInRow) : null
    },

    async upsert(filter, data) {
      const payload = {
        user_id: filter.userId,
        routine_id: filter.routineId,
        date: filter.date,
        status: data.status,
        completed_at: data.completedAt ? new Date(data.completedAt).toISOString() : null,
      } satisfies Partial<SbRoutineCheckInRow>

      const { error: upsertErr, data: out } = await table()
        .upsert(payload, {
          onConflict: 'user_id, routine_id, date',
          ignoreDuplicates: false,
        })
        .select()
        .single()
      throwIfError({ error: upsertErr })
      return toDomain(out as SbRoutineCheckInRow)
    },
  }
}
