import mongoose from 'mongoose'
import TransactionModel from '../../models/Transaction.js'
import type { ITransactionRepository, Transaction, TransactionType } from '../types.js'

type TransactionDoc = {
  _id: { toString(): string }
  user: { toString(): string }
  amount: number
  date: Date
  category: string
  sourceBank: string
  type: string
  description?: string
  createdAt?: Date
  updatedAt?: Date
}

function toDomain(doc: TransactionDoc): Transaction {
  return {
    id: doc._id.toString(),
    userId: doc.user.toString(),
    amount: doc.amount,
    date: doc.date,
    category: doc.category,
    sourceBank: doc.sourceBank,
    type: doc.type as TransactionType,
    description: doc.description,
    createdAt: doc.createdAt,
    updatedAt: doc.updatedAt,
  }
}

function toMongo(data: Partial<Transaction>): Record<string, unknown> {
  const result: Record<string, unknown> = {}
  if (data.userId) result.user = new mongoose.Types.ObjectId(data.userId)
  if (data.amount !== undefined) result.amount = data.amount
  if (data.date !== undefined) result.date = data.date
  if (data.category !== undefined) result.category = data.category
  if (data.sourceBank !== undefined) result.sourceBank = data.sourceBank
  if (data.type !== undefined) result.type = data.type
  if (data.description !== undefined) result.description = data.description
  return result
}

export const transactionRepository: ITransactionRepository = {
  async findByUserId(userId) {
    const docs = await TransactionModel.find({ user: userId }).sort({ date: -1 })
    return docs.map(toDomain)
  },

  async findById(id) {
    const doc = await TransactionModel.findById(id)
    return doc ? toDomain(doc) : null
  },

  async create(data) {
    const doc = await TransactionModel.create(toMongo(data))
    return toDomain(doc)
  },

  async createMany(items) {
    const docs = await TransactionModel.insertMany(items.map((t) => toMongo(t)))
    return docs.map(toDomain)
  },

  async delete(id) {
    await TransactionModel.findByIdAndDelete(id)
  },
}
