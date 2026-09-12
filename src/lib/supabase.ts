import { createClient } from '@supabase/supabase-js'

const url = (import.meta as unknown as { env?: Record<string, string> }).env?.VITE_SUPABASE_URL ?? ''
const anonKey = (import.meta as unknown as { env?: Record<string, string> }).env?.VITE_SUPABASE_ANON_KEY ?? ''

export const supabaseClient = createClient(url, anonKey, {
  auth: {
    persistSession: true,
    autoRefreshToken: true,
  },
})

export type { SupabaseClient } from '@supabase/supabase-js'
