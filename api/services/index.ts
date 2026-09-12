import { repositories } from '../repositories/index.js'
import { createTaskService } from './taskService.js'
import { createFinanceService } from './financeService.js'
import { createRoutineService } from './routineService.js'
import type { Repositories } from '../repositories/types.js'

export function createServices(repos: Repositories = repositories) {
  return {
    task: createTaskService(repos),
    finance: createFinanceService(repos),
    routine: createRoutineService(repos),
  }
}

export const services = createServices()

export type { StatementSource } from './financeService.js'
