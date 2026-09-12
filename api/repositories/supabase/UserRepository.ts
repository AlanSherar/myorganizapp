import { createClient, type SupabaseClient } from '@supabase/supabase-js'
import type { IUserRepository, User } from '../types.js'

type SbUserRow = {
  id: string
  name: string
  email: string
  password_hash: string | null
  created_at: string
  updated_at: string
}

function toDomain(row: SbUserRow): User {
  return {
    id: row.id,
    name: row.name,
    email: row.email,
    password: row.password_hash ?? undefined,
    createdAt: new Date(row.created_at),
    updatedAt: new Date(row.updated_at),
  }
}

function toSupabase(data: Partial<User>): Partial<SbUserRow> {
  const out: Partial<SbUserRow> = {}
  if (data.name !== undefined) out.name = data.name
  if (data.email !== undefined) out.email = data.email
  if (data.password !== undefined) out.password_hash = data.password ?? null
  return out
}

function throwIfError(result: { error: unknown } | null) {
  if (result?.error) {
    throw new Error('[Supabase UserRepository] ' + String((result.error as Error)?.message ?? result.error))
  }
}

export function createUserRepository(client?: SupabaseClient): IUserRepository {
  const sb =
    client ??
    createClient(process.env.SUPABASE_URL ?? '', process.env.SUPABASE_SERVICE_ROLE_KEY ?? '', {
      auth: { persistSession: false, autoRefreshToken: false },
    })

  const table = () => sb.from('users')

  return {
    async findById(id) {
      const { data, error } = await table().select('*').eq('id', id).maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbUserRow) : null
    },

    async findByEmail(email) {
      const { data, error } = await table().select('*').eq('email', email).maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbUserRow) : null
    },

    async create(user) {
      const payload = toSupabase(user) as SbUserRow
      const { data, error } = await table().insert([payload]).select().single()
      throwIfError({ error })
      return toDomain(data as SbUserRow)
    },

    async update(id, updates) {
      const payload = toSupabase(updates) as Partial<SbUserRow>
      const { data, error } = await table().update(payload).eq('id', id).select().maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbUserRow) : null
    },

    async delete(id) {
      const { error } = await table().delete().eq('id', id)
      throwIfError({ error })
    },
  }
}
