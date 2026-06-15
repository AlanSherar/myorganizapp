import { Request, Response } from 'express';
import mongoose from 'mongoose';
import Task, { ITask } from '../models/Task.js';
import { getIntervalBounds, getNextIntervalBounds, type Recurrence } from '../utils/recurrence.js';

// Get all tasks for a user
export const getTasks = async (req: Request, res: Response) => {
  try {
    const userId = req.body.user.id || '60d0fe4f5311236168a109ca';

    const now = new Date();
    const overdue = await Task.find({
      user: userId,
      status: 'pending',
      dueDate: { $exists: true, $lt: now },
    });

    for (const task of overdue) {
      if (task.type === 'recurring' && task.recurrence) {
        const recurrence = task.recurrence as Recurrence;
        const baseDate = task.dueDate || now;
        const bounds = task.intervalStart && task.intervalEnd
          ? { start: task.intervalStart, end: task.intervalEnd }
          : getIntervalBounds(recurrence, baseDate);

        const seriesId = task.seriesId || task._id.toString();
        const intervalStart = bounds.start;
        const intervalEnd = bounds.end;

        await Task.updateOne(
          { _id: task._id },
          { $set: { seriesId, intervalStart, intervalEnd, dueDate: intervalEnd } },
        );

        const nextBounds = getNextIntervalBounds(recurrence, intervalStart);
        const nextExists = await Task.exists({
          user: userId,
          seriesId,
          intervalStart: nextBounds.start,
        });

        if (!nextExists) {
          await Task.create({
            user: task.user,
            title: task.title,
            description: task.description,
            type: 'recurring',
            recurrence: task.recurrence,
            priority: task.priority,
            status: 'pending',
            dueDate: nextBounds.end,
            intervalStart: nextBounds.start,
            intervalEnd: nextBounds.end,
            seriesId,
            keepIfMissed: task.keepIfMissed === true,
          });
        }

        if (task.keepIfMissed === true) {
          await Task.updateOne(
            { _id: task._id },
            { $set: { status: 'missed', missedAt: now } },
          );
        } else {
          await Task.deleteOne({ _id: task._id });
        }
      } else {
        await Task.updateOne(
          { _id: task._id },
          { $set: { status: 'missed', missedAt: now } },
        );
      }
    }

    const tasks = await Task.find({ user: userId }).sort({ dueDate: 1 });
    res.json(tasks);
  } catch (error) {
    res.status(500).json({ message: 'Server Error' });
  }
};

// Create a new task
export const createTask = async (req: Request, res: Response) => {
  try {
    const { title, description, type, recurrence, priority, dueDate, keepIfMissed } = req.body;
    const userId = req.body.user.id || '60d0fe4f5311236168a109ca';

    const baseTask: Record<string, unknown> = {
      user: userId,
      title,
      description,
      type,
      recurrence,
      priority,
      dueDate,
    };

    if (type === 'recurring' && recurrence) {
      const baseDate = dueDate ? new Date(dueDate) : new Date();
      const bounds = getIntervalBounds(recurrence as Recurrence, baseDate);
      baseTask.dueDate = bounds.end;
      baseTask.intervalStart = bounds.start;
      baseTask.intervalEnd = bounds.end;
      baseTask.seriesId = new mongoose.Types.ObjectId().toString();
      baseTask.keepIfMissed = keepIfMissed === true;
      baseTask.status = 'pending';
    }

    const task = new Task(baseTask);
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
    const { user, ...updates } = req.body;
    
    // Check if we are completing a task
    if (updates.status === 'completed') {
        const task = await Task.findById(id);
        if (task && task.type === 'recurring' && task.recurrence) {
            const recurrence = task.recurrence as Recurrence;
            const baseDate = task.dueDate || new Date();
            const bounds = task.intervalStart && task.intervalEnd
              ? { start: task.intervalStart, end: task.intervalEnd }
              : getIntervalBounds(recurrence, baseDate);

            const seriesId = task.seriesId || task._id.toString();
            const intervalStart = bounds.start;
            const intervalEnd = bounds.end;

            await Task.updateOne(
              { _id: task._id },
              { $set: { seriesId, intervalStart, intervalEnd, dueDate: intervalEnd, keepIfMissed: task.keepIfMissed === true } },
            );

            const nextBounds = getNextIntervalBounds(recurrence, intervalStart);
            const nextExists = await Task.exists({
              user: task.user,
              seriesId,
              intervalStart: nextBounds.start,
            });

            if (!nextExists) {
              await Task.create({
                user: task.user,
                title: task.title,
                description: task.description,
                type: 'recurring',
                recurrence: task.recurrence,
                priority: task.priority,
                status: 'pending',
                dueDate: nextBounds.end,
                intervalStart: nextBounds.start,
                intervalEnd: nextBounds.end,
                seriesId,
                keepIfMissed: task.keepIfMissed === true,
              });
            }
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
