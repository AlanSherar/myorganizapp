import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { api } from '../lib/api';

export interface Task {
  _id: string;
  title: string;
  description?: string;
  type: 'one-time' | 'recurring';
  recurrence?: 'daily' | 'weekly' | 'monthly' | 'yearly';
  status: 'pending' | 'completed' | 'missed';
  priority: 'low' | 'medium' | 'high';
  dueDate?: string;
  keepIfMissed?: boolean;
}

interface TaskContextType {
  tasks: Task[];
  fetchTasks: () => Promise<void>;
  addTask: (task: Omit<Task, '_id'>) => Promise<void>;
  updateTask: (id: string, updates: Partial<Task>) => Promise<void>;
  deleteTask: (id: string) => Promise<void>;
}

const TaskContext = createContext<TaskContextType | undefined>(undefined);

export const TaskProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [tasks, setTasks] = useState<Task[]>([]);

  const fetchTasks = useCallback(async () => {
    try {
      const data = await api.get('/tasks');
      setTasks(data);
    } catch (error) {
      console.error('Failed to fetch tasks', error);
    }
  }, []);

  const addTask = async (task: Omit<Task, '_id'>) => {
    await api.post('/tasks', task);
    fetchTasks();
  };

  const updateTask = async (id: string, updates: Partial<Task>) => {
    await api.put(`/tasks/${id}`, updates);
    fetchTasks();
  };

  const deleteTask = async (id: string) => {
    await api.delete(`/tasks/${id}`);
    fetchTasks();
  };

  useEffect(() => {
    fetchTasks();
  }, [fetchTasks]);

  return (
    <TaskContext.Provider value={{ tasks, fetchTasks, addTask, updateTask, deleteTask }}>
      {children}
    </TaskContext.Provider>
  );
};

export const useTasks = () => {
  const context = useContext(TaskContext);
  if (!context) throw new Error('useTasks must be used within a TaskProvider');
  return context;
};
