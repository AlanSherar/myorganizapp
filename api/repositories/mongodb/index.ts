import type { Repositories } from '../types.js'
import { userRepository } from './UserRepository.js'
import { taskRepository } from './TaskRepository.js'
import { taskSeriesRepository } from './TaskSeriesRepository.js'
import { transactionRepository } from './TransactionRepository.js'
import { routineRepository } from './RoutineRepository.js'
import { routineCheckInRepository } from './RoutineCheckInRepository.js'

export function createMongoDBRepositories(): Repositories {
  return {
    user: userRepository,
    task: taskRepository,
    taskSeries: taskSeriesRepository,
    transaction: transactionRepository,
    routine: routineRepository,
    routineCheckIn: routineCheckInRepository,
  }
}

export { userRepository, taskRepository, taskSeriesRepository, transactionRepository, routineRepository, routineCheckInRepository }
