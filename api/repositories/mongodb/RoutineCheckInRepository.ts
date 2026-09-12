import mongoose from 'mongoose'
import RoutineCheckInModel from '../../models/RoutineCheckIn.js'
import type { IRoutineCheckInRepository, RoutineCheckIn, RoutineStatus } from '../types.js'

type RoutineCheckInDoc = {
  _id: { toString(): string }
  user: { toString(): string }
  routine: { toString(): string }
  date: string
  status: string
  completedAt?: Date
  createdAt?: Date
  updatedAt?: Date
}

function toDomain(doc: RoutineCheckInDoc): RoutineCheckIn {
  return {
    id: doc._id.toString(),
    userId: doc.user.toString(),
    routineId: doc.routine.toString(),
    date: doc.date,
    status: doc.status as RoutineStatus,
    completedAt: doc.completedAt,
    createdAt: doc.createdAt,
    updatedAt: doc.updatedAt,
  }
}

export const routineCheckInRepository: IRoutineCheckInRepository = {
  async findByFilter(filter) {
    const query: Record<string, unknown> = { user: filter.userId }
    if (filter.date) query.date = filter.date
    if (filter.status) query.status = filter.status
    if (filter.dateGte || filter.dateLte) {
      query.date = {} as Record<string, unknown>
      if (filter.dateGte) (query.date as Record<string, unknown>).$gte = filter.dateGte
      if (filter.dateLte) (query.date as Record<string, unknown>).$lte = filter.dateLte
    }

    const docs = await RoutineCheckInModel.find(query)
    return docs.map(toDomain)
  },

  async findOneByFilter(filter) {
    const doc = await RoutineCheckInModel.findOne({
      user: filter.userId,
      routine: filter.routineId,
      date: filter.date,
    })
    return doc ? toDomain(doc) : null
  },

  async upsert(filter, data) {
    const doc = await RoutineCheckInModel.findOneAndUpdate(
      {
        user: new mongoose.Types.ObjectId(filter.userId),
        routine: new mongoose.Types.ObjectId(filter.routineId),
        date: filter.date,
      },
      {
        $set: {
          status: data.status,
          completedAt: data.completedAt,
        },
      },
      { upsert: true, new: true },
    )
    return toDomain(doc)
  },
}
