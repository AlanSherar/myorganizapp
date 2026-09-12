import { getDataProvider } from '../config/appConfig.js'
import * as remoteBackend from './remoteBackend.js'
import * as localBackend from './localBackend.js'
import * as supabaseBackend from './supabaseBackend.js'
import type {
  BackendServices,
  Task,
  CreateTaskInput,
  Transaction,
  CreateTransactionInput,
  Routine,
  CreateRoutineInput,
  RoutineCheckIn,
  RoutineStatus,
  TaskRecurrence,
  TaskPriority,
  TaskStatus,
  TaskType,
  TransactionType,
} from './types.js'

function createServices(): BackendServices {
  const provider = getDataProvider()

  if (provider === 'local') {
    return {
      task: localBackend.taskService,
      finance: localBackend.financeService,
      routine: localBackend.routineService,
    }
  }

  if (provider === 'supabase') {
    return {
      task: supabaseBackend.taskService,
      finance: supabaseBackend.financeService,
      routine: supabaseBackend.routineService,
    }
  }

  return {
    task: remoteBackend.taskService,
    finance: remoteBackend.financeService,
    routine: remoteBackend.routineService,
  }
}

export const services = createServices()

export const taskService = services.task
export const financeService = services.finance
export const routineService = services.routine

export type {
  BackendServices,
  Task,
  CreateTaskInput,
  Transaction,
  CreateTransactionInput,
  Routine,
  CreateRoutineInput,
  RoutineCheckIn,
  RoutineStatus,
  TaskRecurrence,
  TaskPriority,
  TaskStatus,
  TaskType,
  TransactionType,
}
