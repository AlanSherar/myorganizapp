import React, { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { taskService, type Task as ServiceTask, type CreateTaskInput, type TaskStatus } from '../services'

export interface Task {
  _id: string
  title: string
  description?: string
  type: 'one-time' | 'recurring'
  recurrence?: 'daily' | 'weekly' | 'monthly' | 'yearly'
  status: 'pending' | 'completed' | 'missed'
  priority: 'low' | 'medium' | 'high'
  dueDate?: string
  keepIfMissed?: boolean
}

interface TaskContextType {
  tasks: Task[]
  fetchTasks: () => Promise<void>
  addTask: (task: Omit<Task, '_id'>) => Promise<void>
  updateTask: (id: string, updates: Partial<Task> & { status?: TaskStatus }) => Promise<void>
  deleteTask: (id: string) => Promise<void>
}

const TaskContext = createContext<TaskContextType | undefined>(undefined)

function toContext(task: ServiceTask): Task {
  return {
    _id: task.id,
    title: task.title,
    description: task.description,
    type: task.type,
    recurrence: task.recurrence,
    status: task.status,
    priority: task.priority,
    dueDate: task.dueDate,
    keepIfMissed: task.keepIfMissed,
  }
}

export const TaskProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [tasks, setTasks] = useState<Task[]>([])

  const fetchTasks = useCallback(async () => {
    try {
      const data = await taskService.getTasks()
      setTasks(data.map(toContext))
    } catch (error) {
      console.error('Failed to fetch tasks', error)
    }
  }, [])

  const addTask = async (task: Omit<Task, '_id'>) => {
    try {
      const input: CreateTaskInput = {
        title: task.title,
        description: task.description,
        type: task.type,
        recurrence: task.recurrence,
        priority: task.priority,
        dueDate: task.dueDate,
        keepIfMissed: task.keepIfMissed,
      }
      await taskService.createTask(input)
      await fetchTasks()
    } catch (error) {
      console.error('Failed to add task', error)
    }
  }

  const updateTask = async (id: string, updates: Partial<Task> & { status?: TaskStatus }) => {
    try {
      const serviceUpdates: Partial<Omit<Task, '_id'>> & { status?: TaskStatus } = { ...updates }
      await taskService.updateTask(id, serviceUpdates)
      await fetchTasks()
    } catch (error) {
      console.error('Failed to update task', error)
    }
  }

  const deleteTask = async (id: string) => {
    try {
      await taskService.deleteTask(id)
      await fetchTasks()
    } catch (error) {
      console.error('Failed to delete task', error)
    }
  }

  useEffect(() => {
    fetchTasks()
  }, [fetchTasks])

  return (
    <TaskContext.Provider value={{ tasks, fetchTasks, addTask, updateTask, deleteTask }}>
      {children}
    </TaskContext.Provider>
  )
}

export const useTasks = () => {
  const context = useContext(TaskContext)
  if (!context) throw new Error('useTasks must be used within a TaskProvider')
  return context
}
