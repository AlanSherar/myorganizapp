import mongoose from 'mongoose'
import RoutineModel from '../../models/Routine.js'
import type { IRoutineRepository, Routine } from '../types.js'

type RoutineDoc = {
  _id: { toString(): string }
  user: { toString(): string }
  title: string
  startTime: string
  endTime: string
  days: number[]
  active: boolean
  createdAt?: Date
  updatedAt?: Date
}

function toDomain(doc: RoutineDoc): Routine {
  return {
    id: doc._id.toString(),
    userId: doc.user.toString(),
    title: doc.title,
    startTime: doc.startTime,
    endTime: doc.endTime,
    days: doc.days,
    active: doc.active,
    createdAt: doc.createdAt,
    updatedAt: doc.updatedAt,
  }
}

function toMongo(data: Partial<Routine>): Record<string, unknown> {
  const result: Record<string, unknown> = {}
  if (data.userId) result.user = new mongoose.Types.ObjectId(data.userId)
  if (data.title !== undefined) result.title = data.title
  if (data.startTime !== undefined) result.startTime = data.startTime
  if (data.endTime !== undefined) result.endTime = data.endTime
  if (data.days !== undefined) result.days = data.days
  if (data.active !== undefined) result.active = data.active
  return result
}

export const routineRepository: IRoutineRepository = {
  async findByUserId(userId) {
    const docs = await RoutineModel.find({ user: userId })
    return docs.map(toDomain)
  },

  async findByUserIdAndActive(userId, active) {
    const docs = await RoutineModel.find({ user: userId, active })
    return docs.map(toDomain)
  },

  async findById(id) {
    const doc = await RoutineModel.findById(id)
    return doc ? toDomain(doc) : null
  },

  async create(data) {
    const doc = await RoutineModel.create(toMongo(data))
    return toDomain(doc)
  },

  async delete(id) {
    await RoutineModel.findByIdAndDelete(id)
  },
}
