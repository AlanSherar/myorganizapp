import type {
  Repositories,
  Task,
  TaskSeries,
  TaskRecurrence,
  TaskPriority,
} from '../repositories/types.js'
import {
  getIntervalBounds,
  getNextIntervalBounds,
  deriveDueRuleFromDate,
  computeDueAtForInterval,
  type Recurrence,
} from '../utils/recurrence.js'

const MAX_BACKFILL_INTERVALS = 60

export function createTaskService(repos: Repositories) {
  const { task, taskSeries } = repos

  async function migrateLegacyRecurringTasks(userId: string) {
    const legacySeriesIds = await task.distinctSeriesIdsByFilter({
      userId,
      type: 'recurring',
      seriesExists: false,
      seriesIdExists: true,
    })

    for (const legacyId of legacySeriesIds) {
      const exists = await taskSeries.exists({ userId, legacySeriesId: legacyId })
      if (exists) continue

      const allTasks = await task.findByUserId(userId)
      const template = allTasks.find(
        (t) =>
          t.type === 'recurring' &&
          t.seriesId === legacyId &&
          t.userId === userId,
      )

      if (!template || !template.recurrence) continue

      const recurrence = template.recurrence as Recurrence
      const baseDate = template.dueDate || new Date()
      const bounds =
        template.intervalStart && template.intervalEnd
          ? { start: template.intervalStart, end: template.intervalEnd }
          : getIntervalBounds(recurrence, baseDate)

      const series = await taskSeries.create({
        userId,
        title: template.title,
        description: template.description,
        recurrence,
        priority: template.priority,
        keepIfMissed: template.keepIfMissed === true,
        dueRule: deriveDueRuleFromDate(recurrence, baseDate),
        startAt: bounds.start,
        lastGeneratedIntervalStart: bounds.start,
        legacySeriesId: legacyId,
      })

      await task.updateManyByFilter(
        { userId, type: 'recurring' },
        { seriesId: series.id },
      )
    }
  }

  async function ensureSeriesInstance(
    userId: string,
    series: TaskSeries,
    intervalStart: Date,
    now: Date,
  ) {
    const recurrence = series.recurrence as Recurrence
    const bounds = getIntervalBounds(recurrence, intervalStart)

    if (series.stopAt && bounds.start.getTime() > new Date(series.stopAt).getTime()) {
      return
    }

    const dueAt = computeDueAtForInterval(recurrence, bounds.start, series.dueRule)
    const status: 'pending' | 'missed' =
      dueAt.getTime() < now.getTime() ? 'missed' : 'pending'
    const missedAt = status === 'missed' ? now : undefined

    const existing = await task.findOneByFilter({
      userId,
      seriesId: series.id,
      intervalStart: bounds.start,
    })

    if (existing) return existing

    return await task.create({
      userId,
      seriesId: series.id,
      title: series.title,
      description: series.description,
      type: 'recurring',
      recurrence,
      priority: series.priority,
      keepIfMissed: series.keepIfMissed,
      status,
      dueDate: dueAt,
      intervalStart: bounds.start,
      intervalEnd: bounds.end,
      missedAt,
    })
  }

  async function syncTaskSeriesForUser(userId: string, now: Date) {
    const seriesList = await taskSeries.findByUserId(userId)

    for (const series of seriesList) {
      const recurrence = series.recurrence as Recurrence
      const currentInterval = getIntervalBounds(recurrence, now)
      const nextInterval = getNextIntervalBounds(recurrence, currentInterval.start)

      const startInterval = getIntervalBounds(recurrence, series.startAt).start
      const last = series.lastGeneratedIntervalStart
        ? getIntervalBounds(recurrence, series.lastGeneratedIntervalStart).start
        : startInterval

      if (series.keepIfMissed) {
        let cursor = last
        let count = 0
        while (
          cursor.getTime() <= currentInterval.start.getTime() &&
          count < MAX_BACKFILL_INTERVALS
        ) {
          await ensureSeriesInstance(userId, series, cursor, now)
          cursor = getNextIntervalBounds(recurrence, cursor).start
          count += 1
        }
        await ensureSeriesInstance(userId, series, nextInterval.start, now)
        series.lastGeneratedIntervalStart = nextInterval.start
        await taskSeries.save(series)
      } else {
        await ensureSeriesInstance(userId, series, currentInterval.start, now)
        await ensureSeriesInstance(userId, series, nextInterval.start, now)
        series.lastGeneratedIntervalStart = nextInterval.start
        await taskSeries.save(series)

        await task.deleteManyByFilter({
          userId,
          seriesId: series.id,
          status: 'missed',
          keepIfMissed: false,
          intervalEndBefore: now,
        })
      }

      await task.updateManyByFilter(
        { userId, seriesId: series.id, status: 'pending', dueDateBefore: now },
        { status: 'missed', missedAt: now },
      )

      if (!series.keepIfMissed) {
        await task.deleteManyByFilter({
          userId,
          seriesId: series.id,
          status: 'missed',
          keepIfMissed: false,
          intervalEndBefore: now,
        })
      }
    }
  }

  async function getAllForUser(userId: string): Promise<Task[]> {
    const now = new Date()

    await task.updateManyByFilter(
      { userId, type: 'one-time', status: 'pending', dueDateBefore: now },
      { status: 'missed', missedAt: now },
    )

    await migrateLegacyRecurringTasks(userId)
    await syncTaskSeriesForUser(userId, now)

    return task.findByUserId(userId)
  }

  async function createTask(
    userId: string,
    input: {
      title: string
      description?: string
      type: 'one-time' | 'recurring'
      recurrence?: TaskRecurrence
      priority: TaskPriority
      dueDate?: string
      keepIfMissed?: boolean
      stopAt?: string
    },
  ): Promise<Task | null> {
    const { title, description, type, recurrence, priority, dueDate, keepIfMissed, stopAt } =
      input

    if (type === 'recurring' && recurrence) {
      const dueAt = dueDate ? new Date(dueDate) : new Date()
      const bounds = getIntervalBounds(recurrence as Recurrence, dueAt)

      const series = await taskSeries.create({
        userId,
        title,
        description,
        recurrence: recurrence as TaskRecurrence,
        priority,
        keepIfMissed: keepIfMissed === true,
        dueRule: deriveDueRuleFromDate(recurrence as Recurrence, dueAt),
        startAt: bounds.start,
        stopAt: stopAt ? new Date(stopAt) : undefined,
        lastGeneratedIntervalStart: bounds.start,
      })

      await ensureSeriesInstance(userId, series, bounds.start, new Date())
      return await task.findOneByFilter({
        userId,
        seriesId: series.id,
        intervalStart: bounds.start,
      })
    }

    return await task.create({
      userId,
      title,
      description,
      type,
      priority,
      dueDate: dueDate ? new Date(dueDate) : undefined,
      status: 'pending',
    })
  }

  async function updateTask(
    userId: string,
    id: string,
    updates: Partial<Task> & { status?: 'pending' | 'completed' | 'missed' },
  ): Promise<Task | null> {
    if (updates.status === 'completed') {
      const taskDoc = await task.findById(id)
      if (taskDoc && taskDoc.seriesId) {
        const series = await taskSeries.findById(taskDoc.seriesId)
        if (series) {
          const recurrence = series.recurrence as Recurrence
          const intervalStart =
            taskDoc.intervalStart ||
            getIntervalBounds(recurrence, taskDoc.dueDate || new Date()).start
          const next = getNextIntervalBounds(recurrence, intervalStart)
          await ensureSeriesInstance(userId, series, next.start, new Date())
          series.lastGeneratedIntervalStart = next.start
          await taskSeries.save(series)
        }
      }
      updates.completedAt = new Date()
    }

    if (updates.status === 'missed' && !updates.missedAt) {
      updates.missedAt = new Date()
    }

    return await task.update(id, updates)
  }

  async function deleteTask(id: string): Promise<void> {
    await task.delete(id)
  }

  return {
    getAllForUser,
    createTask,
    updateTask,
    deleteTask,
  }
}
