import React, { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { routineService, type Routine as ServiceRoutine, type CreateRoutineInput, type RoutineStatus } from '../services'

export interface Routine {
  _id: string
  title: string
  startTime: string
  endTime: string
  days: number[]
  active: boolean
}

interface RoutineContextType {
  routines: Routine[]
  disciplineScore: number
  fetchRoutines: () => Promise<void>
  createRoutine: (routine: Omit<Routine, '_id'>) => Promise<void>
  deleteRoutine: (id: string) => Promise<void>
  checkIn: (routineId: string, status: 'completed' | 'missed', date: string) => Promise<void>
  fetchScore: () => Promise<void>
}

const RoutineContext = createContext<RoutineContextType | undefined>(undefined)

function toContext(r: ServiceRoutine): Routine {
  return {
    _id: r.id,
    title: r.title,
    startTime: r.startTime,
    endTime: r.endTime,
    days: r.days,
    active: r.active,
  }
}

export const RoutineProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [routines, setRoutines] = useState<Routine[]>([])
  const [disciplineScore, setDisciplineScore] = useState<number>(0)

  const fetchRoutines = useCallback(async () => {
    try {
      const data = await routineService.getRoutines()
      setRoutines(data.map(toContext))
    } catch (error) {
      console.error('Failed to fetch routines', error)
    }
  }, [])

  const fetchScore = useCallback(async () => {
    try {
      const result = await routineService.getDisciplineScore()
      setDisciplineScore(result.score)
    } catch (error) {
      console.error('Failed to fetch score', error)
    }
  }, [])

  const createRoutine = async (routine: Omit<Routine, '_id'>) => {
    try {
      const input: CreateRoutineInput = {
        title: routine.title,
        startTime: routine.startTime,
        endTime: routine.endTime,
        days: routine.days,
        active: routine.active,
      }
      await routineService.createRoutine(input)
      await fetchRoutines()
      await fetchScore()
    } catch (error) {
      console.error('Failed to create routine', error)
    }
  }

  const deleteRoutine = async (id: string) => {
    try {
      await routineService.deleteRoutine(id)
      await fetchRoutines()
      await fetchScore()
    } catch (error) {
      console.error('Failed to delete routine', error)
    }
  }

  const checkIn = async (routineId: string, status: RoutineStatus, date: string) => {
    try {
      await routineService.checkIn(routineId, status, date)
      await fetchScore()
    } catch (error) {
      console.error('Failed to check-in routine', error)
    }
  }

  useEffect(() => {
    fetchRoutines()
    fetchScore()
  }, [fetchRoutines, fetchScore])

  return (
    <RoutineContext.Provider
      value={{
        routines,
        disciplineScore,
        fetchRoutines,
        createRoutine,
        deleteRoutine,
        checkIn,
        fetchScore,
      }}
    >
      {children}
    </RoutineContext.Provider>
  )
}

export const useRoutines = () => {
  const context = useContext(RoutineContext)
  if (!context) throw new Error('useRoutines must be used within a RoutineProvider')
  return context
}
