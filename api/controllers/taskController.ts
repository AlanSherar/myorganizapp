import { Request, Response } from 'express';
import Task, { ITask } from '../models/Task.js';

// Get all tasks for a user
export const getTasks = async (req: Request, res: Response) => {
  try {
    const tasks = await Task.find({ user: req.body.user.id || '60d0fe4f5311236168a109ca' }).sort({ dueDate: 1 }); // Mock user ID for now if not in req
    res.json(tasks);
  } catch (error) {
    res.status(500).json({ message: 'Server Error' });
  }
};

// Create a new task
export const createTask = async (req: Request, res: Response) => {
  try {
    const { title, description, type, recurrence, priority, dueDate } = req.body;
    const task = new Task({
      user: req.body.user.id || '60d0fe4f5311236168a109ca',
      title,
      description,
      type,
      recurrence,
      priority,
      dueDate,
    });
    const savedTask = await task.save();
    res.status(201).json(savedTask);
  } catch (error) {
    res.status(400).json({ message: 'Invalid Task Data' });
  }
};

// Update task (including completion logic for recurring)
export const updateTask = async (req: Request, res: Response) => {
  try {
    const { id } = req.params;
    const updates = req.body;
    
    // Check if we are completing a task
    if (updates.status === 'completed') {
        const task = await Task.findById(id);
        if (task && task.type === 'recurring' && task.recurrence) {
            // Logic to create next occurrence
            let nextDate = new Date(task.dueDate || Date.now());
            switch (task.recurrence) {
                case 'daily': nextDate.setDate(nextDate.getDate() + 1); break;
                case 'weekly': nextDate.setDate(nextDate.getDate() + 7); break;
                case 'monthly': nextDate.setMonth(nextDate.getMonth() + 1); break;
                case 'yearly': nextDate.setFullYear(nextDate.getFullYear() + 1); break;
            }
            
            // Create new task
            await Task.create({
                user: task.user,
                title: task.title,
                description: task.description,
                type: 'recurring',
                recurrence: task.recurrence,
                priority: task.priority,
                status: 'pending',
                dueDate: nextDate
            });
        }
        updates.completedAt = new Date();
    }

    const updatedTask = await Task.findByIdAndUpdate(id, updates, { new: true });
    res.json(updatedTask);
  } catch (error) {
    res.status(400).json({ message: 'Update Failed' });
  }
};

export const deleteTask = async (req: Request, res: Response) => {
  try {
    await Task.findByIdAndDelete(req.params.id);
    res.json({ message: 'Task Removed' });
  } catch (error) {
    res.status(500).json({ message: 'Delete Failed' });
  }
};
