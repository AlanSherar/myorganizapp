import mongoose from 'mongoose'
import TaskModel from '../../models/Task.js'
import type { ITaskRepository, Task, TaskStatus, TaskType } from '../types.js'

type TaskDoc = {
  _id: { toString(): string }
  user: { toString(): string }
  series?: { toString(): string }
  seriesId?: string
  title: string
  description?: string
  type: string
  recurrence?: string
  status: string
  priority: string
  dueDate?: Date
  intervalStart?: Date
  intervalEnd?: Date
  keepIfMissed?: boolean
  completedAt?: Date
  missedAt?: Date
  createdAt?: Date
  updatedAt?: Date
}

function toDomain(doc: TaskDoc): Task {
  return {
    id: doc._id.toString(),
    userId: doc.user.toString(),
    seriesId: doc.series ? doc.series.toString() : doc.seriesId,
    title: doc.title,
    description: doc.description,
    type: doc.type as TaskType,
    recurrence: doc.recurrence as import('../types.js').TaskRecurrence | undefined,
    status: doc.status as TaskStatus,
    priority: doc.priority as import('../types.js').TaskPriority,
    dueDate: doc.dueDate,
    intervalStart: doc.intervalStart,
    intervalEnd: doc.intervalEnd,
    keepIfMissed: doc.keepIfMissed,
    completedAt: doc.completedAt,
    missedAt: doc.missedAt,
    createdAt: doc.createdAt,
    updatedAt: doc.updatedAt,
  }
}

function toMongo(userId: string, data: Partial<Task>): Record<string, unknown> {
  const result: Record<string, unknown> = {}
  if (data.userId !== undefined || userId) result.user = new mongoose.Types.ObjectId(data.userId ?? userId)
  if (data.seriesId !== undefined) {
    try {
      result.series = new mongoose.Types.ObjectId(data.seriesId)
    } catch {
      result.seriesId = data.seriesId
    }
  }
  if (data.title !== undefined) result.title = data.title
  if (data.description !== undefined) result.description = data.description
  if (data.type !== undefined) result.type = data.type
  if (data.recurrence !== undefined) result.recurrence = data.recurrence
  if (data.status !== undefined) result.status = data.status
  if (data.priority !== undefined) result.priority = data.priority
  if (data.dueDate !== undefined) result.dueDate = data.dueDate
  if (data.intervalStart !== undefined) result.intervalStart = data.intervalStart
  if (data.intervalEnd !== undefined) result.intervalEnd = data.intervalEnd
  if (data.keepIfMissed !== undefined) result.keepIfMissed = data.keepIfMissed
  if (data.completedAt !== undefined) result.completedAt = data.completedAt
  if (data.missedAt !== undefined) result.missedAt = data.missedAt
  return result
}

export const taskRepository: ITaskRepository = {
  async findByUserId(userId) {
    const docs = await TaskModel.find({ user: userId }).sort({ dueDate: 1 })
    return docs.map(toDomain)
  },

  async findById(id) {
    const doc = await TaskModel.findById(id)
    return doc ? toDomain(doc) : null
  },

  async create(data) {
    const doc = await TaskModel.create(toMongo(data.userId, data))
    return toDomain(doc)
  },

  async createMany(items) {
    const docs = await TaskModel.insertMany(items.map((t) => toMongo(t.userId, t)))
    return docs.map(toDomain)
  },

  async update(id, updates) {
    const updateDoc: Record<string, unknown> = { $set: {} }
    const setData = toMongo('', updates)

    if (updates.status === 'completed') {
      setData.completedAt = updates.completedAt ?? new Date()
    }
    if (updates.status && updates.status !== 'completed') {
      updateDoc.$unset = { completedAt: 1 }
    }
    if (updates.status === 'missed') {
      setData.missedAt = updates.missedAt ?? new Date()
    }
    if (updates.status && updates.status !== 'missed') {
      updateDoc.$unset = { ...(updateDoc.$unset as object), missedAt: 1 }
    }

    updateDoc.$set = setData
    const doc = await TaskModel.findByIdAndUpdate(id, updateDoc, { new: true })
    return doc ? toDomain(doc) : null
  },

  async updateManyByFilter(filter, updates) {
    const query: Record<string, unknown> = {}
    if (filter.userId) query.user = filter.userId
    if (filter.type) query.type = filter.type
    if (filter.status) query.status = filter.status
    if (filter.dueDateBefore) query.dueDate = { $exists: true, $lt: filter.dueDateBefore }
    if (filter.seriesId) query.series = filter.seriesId
    if (filter.keepIfMissed !== undefined) query.keepIfMissed = filter.keepIfMissed
    if (filter.intervalEndBefore) query.intervalEnd = { $lt: filter.intervalEndBefore }

    const mongoUpdates = toMongo(filter.userId, updates)
    const result = await TaskModel.updateMany(query, { $set: mongoUpdates })
    return result.modifiedCount
  },

  async delete(id) {
    await TaskModel.findByIdAndDelete(id)
  },

  async deleteManyByFilter(filter) {
    const query: Record<string, unknown> = {}
    if (filter.userId) query.user = filter.userId
    if (filter.seriesId) query.series = filter.seriesId
    if (filter.status) query.status = filter.status
    if (filter.keepIfMissed !== undefined) query.keepIfMissed = filter.keepIfMissed
    if (filter.intervalEndBefore) query.intervalEnd = { $lt: filter.intervalEndBefore }

    const result = await TaskModel.deleteMany(query)
    return result.deletedCount ?? 0
  },

  async findOneByFilter(filter) {
    const query: Record<string, unknown> = {}
    if (filter.userId) query.user = filter.userId
    if (filter.seriesId) query.series = filter.seriesId
    if (filter.intervalStart) query.intervalStart = filter.intervalStart

    const doc = await TaskModel.findOne(query)
    return doc ? toDomain(doc) : null
  },

  async distinctSeriesIdsByFilter(filter) {
    const query: Record<string, unknown> = {}
    if (filter.userId) query.user = filter.userId
    if (filter.type) query.type = filter.type
    if (filter.seriesExists === false) query.series = { $exists: false }
    if (filter.seriesIdExists === true) {
      query.seriesId = { $exists: true, $ne: null }
    }

    const result = await TaskModel.distinct('seriesId', query)
    return result.filter((v): v is string => typeof v === 'string')
  },
}
