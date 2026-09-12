import UserModel from '../../models/User.js'
import type { IUserRepository, User } from '../types.js'

type UserDoc = {
  _id: { toString(): string }
  name: string
  email: string
  password?: string
  createdAt?: Date
  updatedAt?: Date
}

function toDomain(doc: UserDoc): User {
  return {
    id: doc._id.toString(),
    name: doc.name,
    email: doc.email,
    password: doc.password,
    createdAt: doc.createdAt,
    updatedAt: doc.updatedAt,
  }
}

export const userRepository: IUserRepository = {
  async findById(id) {
    const doc = await UserModel.findById(id)
    return doc ? toDomain(doc) : null
  },

  async findByEmail(email) {
    const doc = await UserModel.findOne({ email })
    return doc ? toDomain(doc) : null
  },

  async create(data) {
    const doc = await UserModel.create(data)
    return toDomain(doc)
  },

  async update(id, updates) {
    const doc = await UserModel.findByIdAndUpdate(id, updates, { new: true })
    return doc ? toDomain(doc) : null
  },

  async delete(id) {
    await UserModel.findByIdAndDelete(id)
  },
}
