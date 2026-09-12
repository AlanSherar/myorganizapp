export type AppMode = 'development' | 'production'
export type DatabaseProvider = 'mongodb' | 'supabase' | 'postgres'

export const APP_MODE_ENV_KEY = 'NODE_ENV'
export const DB_PROVIDER_ENV_KEY = 'DB_PROVIDER'
export const DB_PROVIDER_STORAGE_KEY = 'APP_DB_PROVIDER'

export function getAppMode(): AppMode {
  const envMode = process.env[APP_MODE_ENV_KEY]
  return envMode === 'production' ? 'production' : 'development'
}

export function getDatabaseProvider(): DatabaseProvider {
  const envProvider = process.env[DB_PROVIDER_ENV_KEY]
  if (envProvider === 'supabase' || envProvider === 'postgres' || envProvider === 'mongodb') {
    return envProvider
  }
  return 'mongodb'
}

export function setDatabaseProvider(provider: DatabaseProvider) {
  process.env[DB_PROVIDER_ENV_KEY] = provider
}

export function isMongoDBDatabase() {
  return getDatabaseProvider() === 'mongodb'
}

export function isSupabaseDatabase() {
  return getDatabaseProvider() === 'supabase'
}

export function getDatabaseProviderLabel(provider = getDatabaseProvider()) {
  switch (provider) {
    case 'mongodb':
      return 'MongoDB'
    case 'supabase':
      return 'Supabase'
    case 'postgres':
      return 'PostgreSQL'
    default:
      return 'Desconocido'
  }
}
