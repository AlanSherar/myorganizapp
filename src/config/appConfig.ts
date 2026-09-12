export type AppMode = 'development' | 'production'
export type DataProvider = 'remote' | 'local' | 'supabase'

export const APP_MODE_STORAGE_KEY = 'ORG_APP_MODE'
export const APP_DATA_PROVIDER_STORAGE_KEY = 'ORG_DATA_PROVIDER'
export const APP_MODE_QUERY_KEY = 'mode'
export const APP_DATA_PROVIDER_QUERY_KEY = 'dataProvider'

function getQueryParam(key: string): string | null {
  if (typeof window === 'undefined') return null
  try {
    const url = new URL(window.location.href)
    return url.searchParams.get(key)
  } catch {
    return null
  }
}

function getStoredValue(storageKey: string): string | null {
  if (typeof window === 'undefined' || !window.localStorage) return null
  try {
    return localStorage.getItem(storageKey)
  } catch {
    return null
  }
}

function setStoredValue(storageKey: string, value: string) {
  if (typeof window === 'undefined' || !window.localStorage) return
  try {
    localStorage.setItem(storageKey, value)
  } catch {
    /* ignore */
  }
}

export function getAppMode(): AppMode {
  const fromQuery = getQueryParam(APP_MODE_QUERY_KEY)
  if (fromQuery === 'production' || fromQuery === 'development') {
    setStoredValue(APP_MODE_STORAGE_KEY, fromQuery)
    return fromQuery
  }
  const stored = getStoredValue(APP_MODE_STORAGE_KEY)
  return stored === 'production' ? 'production' : 'development'
}

export function setAppMode(mode: AppMode) {
  setStoredValue(APP_MODE_STORAGE_KEY, mode)
}

export function getDataProvider(): DataProvider {
  const fromQuery = getQueryParam(APP_DATA_PROVIDER_QUERY_KEY)
  if (fromQuery === 'remote' || fromQuery === 'local' || fromQuery === 'supabase') {
    setStoredValue(APP_DATA_PROVIDER_STORAGE_KEY, fromQuery)
    return fromQuery
  }
  const stored = getStoredValue(APP_DATA_PROVIDER_STORAGE_KEY)
  if (stored === 'local') return 'local'
  if (stored === 'supabase') return 'supabase'
  return 'remote'
}

export function setDataProvider(provider: DataProvider) {
  setStoredValue(APP_DATA_PROVIDER_STORAGE_KEY, provider)
}

export function isLocalDataProvider() {
  return getDataProvider() === 'local'
}

export function isRemoteDataProvider() {
  return getDataProvider() === 'remote'
}

export function isSupabaseDataProvider() {
  return getDataProvider() === 'supabase'
}

export function getDataProviderLabel(provider: DataProvider = getDataProvider()) {
  switch (provider) {
    case 'local':
      return 'Datos locales'
    case 'supabase':
      return 'Supabase (Directo)'
    case 'remote':
    default:
      return 'API Remota'
  }
}
