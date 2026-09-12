import mongoose from 'mongoose'
import TaskSeriesModel from '../../models/TaskSeries.js'
import type { ITaskSeriesRepository, TaskSeries, TaskPriority, TaskRecurrence } from '../types.js'

type TaskSeriesDoc = {
  _id: { toString(): string }
  user: { toString(): string }
  title: string
  description?: string
  recurrence: string
  priority: string
  keepIfMissed: boolean
  dueRule: TaskSeries['dueRule']
  startAt: Date
  stopAt?: Date
  lastGeneratedIntervalStart?: Date
  legacySeriesId?: string
  createdAt?: Date
  updatedAt?: Date
}

function toDomain(doc: TaskSeriesDoc): TaskSeries {
  return {
    id: doc._id.toString(),
    userId: doc.user.toString(),
    title: doc.title,
    description: doc.description,
    recurrence: doc.recurrence as TaskRecurrence,
    priority: doc.priority as TaskPriority,
    keepIfMissed: doc.keepIfMissed,
    dueRule: doc.dueRule,
    startAt: doc.startAt,
    stopAt: doc.stopAt,
    lastGeneratedIntervalStart: doc.lastGeneratedIntervalStart,
    legacySeriesId: doc.legacySeriesId,
    createdAt: doc.createdAt,
    updatedAt: doc.updatedAt,
  }
}

function toMongo(data: Partial<TaskSeries>): Record<string, unknown> {
  const result: Record<string, unknown> = {}
  if (data.userId) result.user = new mongoose.Types.ObjectId(data.userId)
  if (data.title !== undefined) result.title = data.title
  if (data.description !== undefined) result.description = data.description
  if (data.recurrence !== undefined) result.recurrence = data.recurrence
  if (data.priority !== undefined) result.priority = data.priority
  if (data.keepIfMissed !== undefined) result.keepIfMissed = data.keepIfMissed
  if (data.dueRule !== undefined) result.dueRule = data.dueRule
  if (data.startAt !== undefined) result.startAt = data.startAt
  if (data.stopAt !== undefined) result.stopAt = data.stopAt
  if (data.lastGeneratedIntervalStart !== undefined) result.lastGeneratedIntervalStart = data.lastGeneratedIntervalStart
  if (data.legacySeriesId !== undefined) result.legacySeriesId = data.legacySeriesId
  return result
}

export const taskSeriesRepository: ITaskSeriesRepository = {
  async findByUserId(userId) {
    const docs = await TaskSeriesModel.find({ user: userId })
    return docs.map(toDomain)
  },

  async findById(id) {
    const doc = await TaskSeriesModel.findById(id)
    return doc ? toDomain(doc) : null
  },

  async findOneByFilter(filter) {
    const query: Record<string, unknown> = { user: filter.userId }
    if (filter.legacySeriesId !== undefined) query.legacySeriesId = filter.legacySeriesId
    const doc = await TaskSeriesModel.findOne(query)
    return doc ? toDomain(doc) : null
  },

  async exists(filter) {
    const query: Record<string, unknown> = { user: filter.userId }
    if (filter.legacySeriesId !== undefined) query.legacySeriesId = filter.legacySeriesId
    const result = await TaskSeriesModel.exists(query)
    return !!result
  },

  async create(data) {
    const doc = await TaskSeriesModel.create(toMongo(data))
    return toDomain(doc)
  },

  async update(id, updates) {
    const doc = await TaskSeriesModel.findByIdAndUpdate(id, toMongo(updates), { new: true })
    return doc ? toDomain(doc) : null
  },

  async save(series) {
    const doc = await TaskSeriesModel.findById(series.id)
    if (!doc) throw new Error('TaskSeries not found for save')
    Object.assign(doc, toMongo(series))
    await doc.save()
    return toDomain(doc)
  },
}
