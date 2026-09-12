import { getDatabaseProvider } from '../config/appConfig.js'
import { createMongoDBRepositories } from './mongodb/index.js'
import { createSupabaseRepositories } from './supabase/index.js'
import type { Repositories, RepositoriesFactory } from './types.js'

const providerRepositoriesFactories: Record<string, RepositoriesFactory> = {
  mongodb: createMongoDBRepositories,
  supabase: createSupabaseRepositories,
}

function getRepositoryFactory(provider: string): RepositoriesFactory {
  const factory = providerRepositoriesFactories[provider]
  if (factory) return factory

  console.warn(
    `[Repositories Factory] Proveedor '${provider}' no implementado. Fallback a 'mongodb'. ` +
    `Proveedores disponibles: ${Object.keys(providerRepositoriesFactories).join(', ')}.`,
  )
  return createMongoDBRepositories
}

export function createRepositories(): Repositories {
  const provider = getDatabaseProvider()
  const factory = getRepositoryFactory(provider)
  return factory()
}

export function registerRepositoryFactory(provider: string, factory: RepositoriesFactory) {
  providerRepositoriesFactories[provider] = factory
}

export const repositories = createRepositories()

export type {
  User,
  Task,
  TaskSeries,
  Transaction,
  Routine,
  RoutineCheckIn,
  TaskPriority,
  TaskRecurrence,
  TaskStatus,
  TaskType,
  TransactionType,
  RoutineStatus,
  Repositories,
} from './types.js'
