import { Request, Response } from 'express'
import { services } from '../services/index.js'
import type { RoutineStatus } from '../repositories/types.js'

const DEFAULT_USER_ID = '60d0fe4f5311236168a109ca'

export const getRoutines = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const routines = await services.routine.getRoutines(userId)
    res.json(routines)
  } catch (error) {
    console.error('[routineController.getRoutines]', error)
    res.status(500).json({ message: 'Server Error' })
  }
}

export const createRoutine = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const { title, startTime, endTime, days, active } = req.body
    const routine = await services.routine.createRoutine(userId, {
      title,
      startTime,
      endTime,
      days,
      active,
    })
    res.status(201).json(routine)
  } catch (error) {
    console.error('[routineController.createRoutine]', error)
    res.status(400).json({ message: 'Invalid Data' })
  }
}

export const deleteRoutine = async (req: Request, res: Response) => {
  try {
    await services.routine.deleteRoutine(req.params.id)
    res.json({ message: 'Routine Removed' })
  } catch (error) {
    console.error('[routineController.deleteRoutine]', error)
    res.status(500).json({ message: 'Delete Failed' })
  }
}

export const checkIn = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const { routineId, date, status }: { routineId: string; date: string; status: RoutineStatus } =
      req.body
    const result = await services.routine.checkIn(userId, routineId, date, status)
    res.json(result)
  } catch (error) {
    console.error('[routineController.checkIn]', error)
    res.status(400).json({ message: 'Check-in Failed' })
  }
}

export const getCheckIns = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const date = req.query.date as string | undefined
    const checkIns = await services.routine.getCheckIns(userId, { date })
    res.json(checkIns)
  } catch (error) {
    console.error('[routineController.getCheckIns]', error)
    res.status(500).json({ message: 'Server Error' })
  }
}

export const getDisciplineScore = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const startDate = req.query.startDate as string | undefined
    const endDate = req.query.endDate as string | undefined
    const result = await services.routine.getDisciplineScore(userId, { startDate, endDate })
    res.json(result)
  } catch (error) {
    console.error('[routineController.getDisciplineScore]', error)
    res.status(500).json({ message: 'Calculation Failed' })
  }
}
