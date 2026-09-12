export type TaskType = 'one-time' | 'recurring'
export type TaskRecurrence = 'daily' | 'weekly' | 'monthly' | 'yearly'
export type TaskStatus = 'pending' | 'completed' | 'missed'
export type TaskPriority = 'low' | 'medium' | 'high'

export type TransactionType = 'income' | 'expense'

export type RoutineStatus = 'completed' | 'missed'

export interface User {
  id: string
  name: string
  email: string
  password?: string
  createdAt: Date
  updatedAt: Date
}

export interface Task {
  id: string
  userId: string
  seriesId?: string
  title: string
  description?: string
  type: TaskType
  recurrence?: TaskRecurrence
  status: TaskStatus
  priority: TaskPriority
  dueDate?: Date
  intervalStart?: Date
  intervalEnd?: Date
  keepIfMissed?: boolean
  completedAt?: Date
  missedAt?: Date
  createdAt: Date
  updatedAt: Date
}

export interface TaskSeriesDueRule {
  time: string
  dayOfWeek?: number
  dayOfMonth?: number
  month?: number
}

export interface TaskSeries {
  id: string
  userId: string
  title: string
  description?: string
  recurrence: TaskRecurrence
  priority: TaskPriority
  keepIfMissed: boolean
  dueRule: TaskSeriesDueRule
  startAt: Date
  stopAt?: Date
  lastGeneratedIntervalStart?: Date
  legacySeriesId?: string
  createdAt: Date
  updatedAt: Date
}

export interface Transaction {
  id: string
  userId: string
  amount: number
  date: Date
  category: string
  sourceBank: string
  type: TransactionType
  description?: string
  createdAt: Date
  updatedAt: Date
}

export interface Routine {
  id: string
  userId: string
  title: string
  startTime: string
  endTime: string
  days: number[]
  active: boolean
  createdAt: Date
  updatedAt: Date
}

export interface RoutineCheckIn {
  id: string
  userId: string
  routineId: string
  date: string
  status: RoutineStatus
  completedAt?: Date
  createdAt: Date
  updatedAt: Date
}

export interface ServiceResult<T = unknown, E = unknown> {
  data: T | null
  error: E | null
}

export interface IUserRepository {
  findById(id: string): Promise<User | null>
  findByEmail(email: string): Promise<User | null>
  create(user: Omit<User, 'id' | 'createdAt' | 'updatedAt'>): Promise<User>
  update(id: string, updates: Partial<User>): Promise<User | null>
  delete(id: string): Promise<void>
}

export interface ITaskRepository {
  findByUserId(userId: string): Promise<Task[]>
  findById(id: string): Promise<Task | null>
  create(task: Omit<Task, 'id' | 'createdAt' | 'updatedAt'>): Promise<Task>
  createMany(tasks: Array<Omit<Task, 'id' | 'createdAt' | 'updatedAt'>>): Promise<Task[]>
  update(id: string, updates: Partial<Task>): Promise<Task | null>
  updateManyByFilter(
    filter: { userId: string; type?: TaskType; status?: TaskStatus; dueDateBefore?: Date; seriesId?: string; keepIfMissed?: boolean; intervalEndBefore?: Date },
    updates: Partial<Task>,
  ): Promise<number>
  delete(id: string): Promise<void>
  deleteManyByFilter(
    filter: { userId: string; seriesId?: string; status?: TaskStatus; keepIfMissed?: boolean; intervalEndBefore?: Date },
  ): Promise<number>
  findOneByFilter(
    filter: { userId: string; seriesId?: string; intervalStart?: Date },
  ): Promise<Task | null>
  distinctSeriesIdsByFilter(
    filter: { userId: string; type: TaskType; seriesExists: boolean; seriesIdExists: boolean },
  ): Promise<string[]>
}

export interface ITaskSeriesRepository {
  findByUserId(userId: string): Promise<TaskSeries[]>
  findById(id: string): Promise<TaskSeries | null>
  findOneByFilter(filter: { userId: string; legacySeriesId?: string }): Promise<TaskSeries | null>
  exists(filter: { userId: string; legacySeriesId?: string }): Promise<boolean>
  create(series: Omit<TaskSeries, 'id' | 'createdAt' | 'updatedAt'>): Promise<TaskSeries>
  update(id: string, updates: Partial<TaskSeries>): Promise<TaskSeries | null>
  save(series: TaskSeries): Promise<TaskSeries>
}

export interface ITransactionRepository {
  findByUserId(userId: string): Promise<Transaction[]>
  findById(id: string): Promise<Transaction | null>
  create(transaction: Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>): Promise<Transaction>
  createMany(transactions: Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>>): Promise<Transaction[]>
  delete(id: string): Promise<void>
}

export interface IRoutineRepository {
  findByUserId(userId: string): Promise<Routine[]>
  findByUserIdAndActive(userId: string, active: boolean): Promise<Routine[]>
  findById(id: string): Promise<Routine | null>
  create(routine: Omit<Routine, 'id' | 'createdAt' | 'updatedAt'>): Promise<Routine>
  delete(id: string): Promise<void>
}

export interface IRoutineCheckInRepository {
  findByFilter(filter: { userId: string; date?: string; status?: RoutineStatus; dateGte?: string; dateLte?: string }): Promise<RoutineCheckIn[]>
  findOneByFilter(filter: { userId: string; routineId: string; date: string }): Promise<RoutineCheckIn | null>
  upsert(
    filter: { userId: string; routineId: string; date: string },
    data: { status: RoutineStatus; completedAt: Date },
  ): Promise<RoutineCheckIn>
}

export interface Repositories {
  user: IUserRepository
  task: ITaskRepository
  taskSeries: ITaskSeriesRepository
  transaction: ITransactionRepository
  routine: IRoutineRepository
  routineCheckIn: IRoutineCheckInRepository
}

export type RepositoriesFactory = () => Repositories
