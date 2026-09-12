export type TaskType = 'one-time' | 'recurring'
export type TaskRecurrence = 'daily' | 'weekly' | 'monthly' | 'yearly'
export type TaskStatus = 'pending' | 'completed' | 'missed'
export type TaskPriority = 'low' | 'medium' | 'high'
export type TransactionType = 'income' | 'expense'
export type RoutineStatus = 'completed' | 'missed'

export interface ServiceResult<T = unknown, E = unknown> {
  data: T | null
  error: E | null
}

export interface Task {
  id: string
  title: string
  description?: string
  type: TaskType
  recurrence?: TaskRecurrence
  status: TaskStatus
  priority: TaskPriority
  dueDate?: string
  intervalStart?: string
  intervalEnd?: string
  seriesId?: string
  keepIfMissed?: boolean
  completedAt?: string
  missedAt?: string
  createdAt: string
  updatedAt: string
}

export interface CreateTaskInput {
  title: string
  description?: string
  type: TaskType
  recurrence?: TaskRecurrence
  priority: TaskPriority
  dueDate?: string
  keepIfMissed?: boolean
  stopAt?: string
}

export interface Transaction {
  id: string
  amount: number
  date: string
  category: string
  sourceBank: string
  type: TransactionType
  description?: string
  createdAt: string
  updatedAt: string
}

export interface CreateTransactionInput {
  amount: number
  date: string
  category: string
  sourceBank: string
  type: TransactionType
  description?: string
}

export interface Routine {
  id: string
  title: string
  startTime: string
  endTime: string
  days: number[]
  active: boolean
  createdAt: string
  updatedAt: string
}

export interface CreateRoutineInput {
  title: string
  startTime: string
  endTime: string
  days: number[]
  active?: boolean
}

export interface RoutineCheckIn {
  id: string
  routineId: string
  date: string
  status: RoutineStatus
  completedAt?: string
  createdAt: string
  updatedAt: string
}

export interface ITaskService {
  getTasks(): Promise<Task[]>
  createTask(input: CreateTaskInput): Promise<Task>
  updateTask(id: string, updates: Partial<Task> & { status?: TaskStatus }): Promise<Task>
  deleteTask(id: string): Promise<void>
}

export interface IFinanceService {
  getTransactions(): Promise<Transaction[]>
  addTransaction(input: CreateTransactionInput): Promise<Transaction>
  deleteTransaction(id: string): Promise<void>
  uploadStatement(source: string, data: string | unknown): Promise<{ imported: number; transactions: Transaction[] }>
}

export interface IRoutineService {
  getRoutines(): Promise<Routine[]>
  createRoutine(input: CreateRoutineInput): Promise<Routine>
  deleteRoutine(id: string): Promise<void>
  checkIn(routineId: string, status: RoutineStatus, date: string): Promise<RoutineCheckIn>
  getCheckIns(options?: { date?: string }): Promise<RoutineCheckIn[]>
  getDisciplineScore(options?: { startDate?: string; endDate?: string }): Promise<{ score: number }>
}

export interface BackendServices {
  task: ITaskService
  finance: IFinanceService
  routine: IRoutineService
}
