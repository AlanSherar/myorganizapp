import { createClient, type SupabaseClient } from '@supabase/supabase-js'
import type {
  ITransactionRepository,
  Transaction,
  TransactionType,
} from '../types.js'

type SbTransactionRow = {
  id: string
  user_id: string
  amount: number
  date: string
  category: string
  source_bank: string
  type: TransactionType
  description: string | null
  created_at: string
  updated_at: string
}

function toDomain(row: SbTransactionRow): Transaction {
  return {
    id: row.id,
    userId: row.user_id,
    amount: Number(row.amount),
    date: new Date(row.date),
    category: row.category,
    sourceBank: row.source_bank,
    type: row.type,
    description: row.description ?? undefined,
    createdAt: new Date(row.created_at),
    updatedAt: new Date(row.updated_at),
  }
}

function toSupabase(
  data: Partial<Transaction>,
  userId?: string,
): Partial<SbTransactionRow> {
  const out: Partial<SbTransactionRow> = {}
  if (userId) out.user_id = userId
  if (data.userId !== undefined) out.user_id = data.userId
  if (data.amount !== undefined) out.amount = Number(data.amount)
  if (data.date !== undefined) out.date = new Date(data.date).toISOString()
  if (data.category !== undefined) out.category = data.category
  if (data.sourceBank !== undefined) out.source_bank = data.sourceBank
  if (data.type !== undefined) out.type = data.type
  if (data.description !== undefined) out.description = data.description ?? null
  return out
}

function throwIfError(result: { error: unknown } | null) {
  if (result?.error) {
    throw new Error('[Supabase TransactionRepository] ' + String((result.error as Error)?.message ?? result.error))
  }
}

export function createTransactionRepository(client?: SupabaseClient): ITransactionRepository {
  const sb =
    client ??
    createClient(process.env.SUPABASE_URL ?? '', process.env.SUPABASE_SERVICE_ROLE_KEY ?? '', {
      auth: { persistSession: false, autoRefreshToken: false },
    })

  const table = () => sb.from('transactions')

  return {
    async findByUserId(userId) {
      const { data, error } = await table().select('*').eq('user_id', userId).order('date', { ascending: false })
      throwIfError({ error })
      return (data as SbTransactionRow[] | null ?? []).map(toDomain)
    },

    async findById(id) {
      const { data, error } = await table().select('*').eq('id', id).maybeSingle()
      throwIfError({ error })
      return data ? toDomain(data as SbTransactionRow) : null
    },

    async create(transaction) {
      const payload = toSupabase(transaction, transaction.userId) as SbTransactionRow
      const { data, error } = await table().insert([payload]).select().single()
      throwIfError({ error })
      return toDomain(data as SbTransactionRow)
    },

    async createMany(transactions) {
      if (transactions.length === 0) return []
      const payload = transactions.map((t) => toSupabase(t, t.userId)) as SbTransactionRow[]
      const { data, error } = await table().insert(payload).select()
      throwIfError({ error })
      return (data as SbTransactionRow[] | null ?? []).map(toDomain)
    },

    async delete(id) {
      const { error } = await table().delete().eq('id', id)
      throwIfError({ error })
    },
  }
}
