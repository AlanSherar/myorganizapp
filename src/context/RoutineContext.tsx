import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { api } from '../lib/api';

export interface Routine {
  _id: string;
  title: string;
  startTime: string;
  endTime: string;
  days: number[];
  active: boolean;
}

interface RoutineContextType {
  routines: Routine[];
  disciplineScore: number;
  fetchRoutines: () => Promise<void>;
  createRoutine: (routine: Omit<Routine, '_id'>) => Promise<void>;
  deleteRoutine: (id: string) => Promise<void>;
  checkIn: (routineId: string, status: 'completed' | 'missed', date: string) => Promise<void>;
  fetchScore: () => Promise<void>;
}

const RoutineContext = createContext<RoutineContextType | undefined>(undefined);

export const RoutineProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [routines, setRoutines] = useState<Routine[]>([]);
  const [disciplineScore, setDisciplineScore] = useState<number>(0);

  const fetchRoutines = useCallback(async () => {
    try {
      const data = await api.get('/routines');
      setRoutines(data);
    } catch (error) {
      console.error('Failed to fetch routines', error);
    }
  }, []);

  const fetchScore = useCallback(async () => {
    try {
      const data = await api.get('/routines/score');
      setDisciplineScore(data.score);
    } catch (error) {
      console.error('Failed to fetch score', error);
    }
  }, []);

  const createRoutine = async (routine: Omit<Routine, '_id'>) => {
    await api.post('/routines', routine);
    fetchRoutines();
    fetchScore();
  };

  const deleteRoutine = async (id: string) => {
    await api.delete(`/routines/${id}`);
    fetchRoutines();
    fetchScore();
  };

  const checkIn = async (routineId: string, status: 'completed' | 'missed', date: string) => {
    await api.post('/routines/checkin', { routineId, status, date });
    fetchScore();
  };

  useEffect(() => {
    fetchRoutines();
    fetchScore();
  }, [fetchRoutines, fetchScore]);

  return (
    <RoutineContext.Provider value={{ routines, disciplineScore, fetchRoutines, createRoutine, deleteRoutine, checkIn, fetchScore }}>
      {children}
    </RoutineContext.Provider>
  );
};

export const useRoutines = () => {
  const context = useContext(RoutineContext);
  if (!context) throw new Error('useRoutines must be used within a RoutineProvider');
  return context;
};
