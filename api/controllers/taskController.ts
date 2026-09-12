import { Request, Response } from 'express'
import { services } from '../services/index.js'

const DEFAULT_USER_ID = '60d0fe4f5311236168a109ca'

export const getTasks = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const tasks = await services.task.getAllForUser(userId)
    res.json(tasks)
  } catch (error) {
    console.error('[taskController.getTasks]', error)
    res.status(500).json({ message: 'Server Error' })
  }
}

export const createTask = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const { title, description, type, recurrence, priority, dueDate, keepIfMissed, stopAt } =
      req.body
    const created = await services.task.createTask(userId, {
      title,
      description,
      type,
      recurrence,
      priority,
      dueDate,
      keepIfMissed,
      stopAt,
    })
    if (!created) {
      res.status(400).json({ message: 'Invalid Task Data' })
      return
    }
    res.status(201).json(created)
  } catch (error) {
    console.error('[taskController.createTask]', error)
    res.status(400).json({ message: 'Invalid Task Data' })
  }
}

export const updateTask = async (req: Request, res: Response) => {
  try {
    const { id } = req.params
    const { user, ...updates } = req.body
    const userId = (user?.id as string) || DEFAULT_USER_ID
    const updated = await services.task.updateTask(userId, id, updates)
    if (!updated) {
      res.status(404).json({ message: 'Task not found' })
      return
    }
    res.json(updated)
  } catch (error) {
    console.error('[taskController.updateTask]', error)
    res.status(400).json({ message: 'Update Failed' })
  }
}

export const deleteTask = async (req: Request, res: Response) => {
  try {
    await services.task.deleteTask(req.params.id)
    res.json({ message: 'Task Removed' })
  } catch (error) {
    console.error('[taskController.deleteTask]', error)
    res.status(500).json({ message: 'Delete Failed' })
  }
}
