import type {
  Repositories,
  Routine,
  RoutineCheckIn,
  RoutineStatus,
} from '../repositories/types.js'

export function createRoutineService(repos: Repositories) {
  const { routine, routineCheckIn } = repos

  async function getRoutines(userId: string): Promise<Routine[]> {
    return routine.findByUserId(userId)
  }

  async function createRoutine(
    userId: string,
    data: {
      title: string
      startTime: string
      endTime: string
      days: number[]
      active?: boolean
    },
  ): Promise<Routine> {
    return routine.create({
      userId,
      title: data.title,
      startTime: data.startTime,
      endTime: data.endTime,
      days: data.days,
      active: data.active ?? true,
    })
  }

  async function deleteRoutine(id: string): Promise<void> {
    await routine.delete(id)
  }

  async function checkIn(
    userId: string,
    routineId: string,
    date: string,
    status: RoutineStatus,
  ): Promise<RoutineCheckIn> {
    return routineCheckIn.upsert(
      { userId, routineId, date },
      { status, completedAt: new Date() },
    )
  }

  async function getCheckIns(
    userId: string,
    options?: { date?: string },
  ): Promise<RoutineCheckIn[]> {
    return routineCheckIn.findByFilter({
      userId,
      date: options?.date,
    })
  }

  async function getDisciplineScore(
    userId: string,
    options?: { startDate?: string; endDate?: string },
  ): Promise<{ score: number }> {
    const today = new Date().toISOString().split('T')[0]
    const start = options?.startDate || today
    const end = options?.endDate || today

    const activeRoutines = await routine.findByUserIdAndActive(userId, true)
    const completedCheckIns = await routineCheckIn.findByFilter({
      userId,
      dateGte: start,
      dateLte: end,
      status: 'completed',
    })

    const totalRoutines = activeRoutines.length
    if (totalRoutines === 0) return { score: 0 }

    const score = (completedCheckIns.length / totalRoutines) * 100
    return { score: Math.min(score, 100) }
  }

  return {
    getRoutines,
    createRoutine,
    deleteRoutine,
    checkIn,
    getCheckIns,
    getDisciplineScore,
  }
}
