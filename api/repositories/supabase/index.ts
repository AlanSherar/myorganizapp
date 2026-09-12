import type { Repositories } from '../types.js'
import { createUserRepository } from './UserRepository.js'
import { createTaskRepository } from './TaskRepository.js'
import { createTaskSeriesRepository } from './TaskSeriesRepository.js'
import { createTransactionRepository } from './TransactionRepository.js'
import { createRoutineRepository } from './RoutineRepository.js'
import { createRoutineCheckInRepository } from './RoutineCheckInRepository.js'
import { createClient, type SupabaseClient } from '@supabase/supabase-js'

export function createSupabaseRepositories(customClient?: SupabaseClient): Repositories {
  const sharedClient =
    customClient ??
    createClient(process.env.SUPABASE_URL ?? '', process.env.SUPABASE_SERVICE_ROLE_KEY ?? '', {
      auth: { persistSession: false, autoRefreshToken: false },
    })

  return {
    user: createUserRepository(sharedClient),
    task: createTaskRepository(sharedClient),
    taskSeries: createTaskSeriesRepository(sharedClient),
    transaction: createTransactionRepository(sharedClient),
    routine: createRoutineRepository(sharedClient),
    routineCheckIn: createRoutineCheckInRepository(sharedClient),
  }
}
