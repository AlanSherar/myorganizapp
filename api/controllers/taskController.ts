import { Request, Response } from 'express';
import mongoose from 'mongoose';
import Task, { ITask } from '../models/Task.js';
import TaskSeries, { type ITaskSeries, type TaskRecurrence } from '../models/TaskSeries.js';
import {
  getIntervalBounds,
  getNextIntervalBounds,
  type Recurrence,
  deriveDueRuleFromDate,
  computeDueAtForInterval,
} from '../utils/recurrence.js';

const MAX_BACKFILL_INTERVALS = 60;

const migrateLegacyRecurringTasks = async (userId: string) => {
  const legacySeriesIds = await Task.distinct('seriesId', {
    user: userId,
    type: 'recurring',
    series: { $exists: false },
    seriesId: { $exists: true, $ne: null },
  });

  for (const legacyId of legacySeriesIds) {
    const exists = await TaskSeries.exists({ user: userId, legacySeriesId: legacyId });
    if (exists) continue;

    const template = await Task.findOne({
      user: userId,
      type: 'recurring',
      series: { $exists: false },
      seriesId: legacyId,
    });

    if (!template || !template.recurrence) continue;

    const recurrence = template.recurrence as Recurrence;
    const baseDate = template.dueDate || new Date();
    const bounds = template.intervalStart && template.intervalEnd ? { start: template.intervalStart, end: template.intervalEnd } : getIntervalBounds(recurrence, baseDate);
    const series = await TaskSeries.create({
      user: template.user,
      title: template.title,
      description: template.description,
      recurrence,
      priority: template.priority,
      keepIfMissed: template.keepIfMissed === true,
      dueRule: deriveDueRuleFromDate(recurrence, baseDate),
      startAt: bounds.start,
      lastGeneratedIntervalStart: bounds.start,
      legacySeriesId: legacyId,
    });

    await Task.updateMany(
      { user: userId, type: 'recurring', series: { $exists: false }, seriesId: legacyId },
      { $set: { series: series._id } },
    );
  }
};

const ensureSeriesInstance = async (userId: string, series: ITaskSeries, intervalStart: Date, now: Date) => {
  const recurrence = series.recurrence as Recurrence;
  const bounds = getIntervalBounds(recurrence, intervalStart);

  if (series.stopAt && bounds.start.getTime() > new Date(series.stopAt).getTime()) return;

  const dueAt = computeDueAtForInterval(recurrence, bounds.start, series.dueRule);
  const status: 'pending' | 'missed' = dueAt.getTime() < now.getTime() ? 'missed' : 'pending';
  const missedAt = status === 'missed' ? now : undefined;

  await Task.updateOne(
    { user: userId, series: series._id, intervalStart: bounds.start },
    {
      $setOnInsert: {
        user: series.user,
        series: series._id,
        title: series.title,
        description: series.description,
        type: 'recurring',
        recurrence: recurrence,
        priority: series.priority,
        keepIfMissed: series.keepIfMissed,
        status,
        dueDate: dueAt,
        intervalStart: bounds.start,
        intervalEnd: bounds.end,
        missedAt,
      },
    },
    { upsert: true },
  );
};

const syncTaskSeriesForUser = async (userId: string, now: Date) => {
  const seriesList = await TaskSeries.find({ user: userId });
  for (const series of seriesList) {
    const recurrence = series.recurrence as Recurrence;
    const currentInterval = getIntervalBounds(recurrence, now);
    const nextInterval = getNextIntervalBounds(recurrence, currentInterval.start);

    const startInterval = getIntervalBounds(recurrence, series.startAt).start;
    const last = series.lastGeneratedIntervalStart ? getIntervalBounds(recurrence, series.lastGeneratedIntervalStart).start : startInterval;

    if (series.keepIfMissed) {
      let cursor = last;
      let count = 0;
      while (cursor.getTime() <= currentInterval.start.getTime() && count < MAX_BACKFILL_INTERVALS) {
        await ensureSeriesInstance(userId, series as any, cursor, now);
        cursor = getNextIntervalBounds(recurrence, cursor).start;
        count += 1;
      }
      await ensureSeriesInstance(userId, series as any, nextInterval.start, now);
      series.lastGeneratedIntervalStart = nextInterval.start;
      await series.save();
    } else {
      await ensureSeriesInstance(userId, series as any, currentInterval.start, now);
      await ensureSeriesInstance(userId, series as any, nextInterval.start, now);
      series.lastGeneratedIntervalStart = nextInterval.start;
      await series.save();

      await Task.deleteMany({
        user: userId,
        series: series._id,
        status: 'missed',
        keepIfMissed: false,
        intervalEnd: { $lt: now },
      });
    }

    await Task.updateMany(
      { user: userId, series: series._id, status: 'pending', dueDate: { $exists: true, $lt: now } },
      { $set: { status: 'missed', missedAt: now } },
    );

    if (!series.keepIfMissed) {
      await Task.deleteMany({
        user: userId,
        series: series._id,
        status: 'missed',
        keepIfMissed: false,
        intervalEnd: { $lt: now },
      });
    }
  }
};

// Get all tasks for a user
export const getTasks = async (req: Request, res: Response) => {
  try {
    const userId = req.body.user.id || '60d0fe4f5311236168a109ca';

    const now = new Date();
    await Task.updateMany(
      { user: userId, type: 'one-time', status: 'pending', dueDate: { $exists: true, $lt: now } },
      { $set: { status: 'missed', missedAt: now } },
    );

    await migrateLegacyRecurringTasks(userId);
    await syncTaskSeriesForUser(userId, now);

    const tasks = await Task.find({ user: userId }).sort({ dueDate: 1 });
    res.json(tasks);
  } catch (error) {
    res.status(500).json({ message: 'Server Error' });
  }
};

// Create a new task
export const createTask = async (req: Request, res: Response) => {
  try {
    const { title, description, type, recurrence, priority, dueDate, keepIfMissed, stopAt } = req.body;
    const userId = req.body.user.id || '60d0fe4f5311236168a109ca';

    if (type === 'recurring' && recurrence) {
      const dueAt = dueDate ? new Date(dueDate) : new Date();
      const bounds = getIntervalBounds(recurrence as Recurrence, dueAt);
      const series = await TaskSeries.create({
        user: userId,
        title,
        description,
        recurrence: recurrence as TaskRecurrence,
        priority,
        keepIfMissed: keepIfMissed === true,
        dueRule: deriveDueRuleFromDate(recurrence as Recurrence, dueAt),
        startAt: bounds.start,
        stopAt: stopAt ? new Date(stopAt) : undefined,
        lastGeneratedIntervalStart: bounds.start,
      });

      await ensureSeriesInstance(userId, series as any, bounds.start, new Date());
      const saved = await Task.findOne({ user: userId, series: series._id, intervalStart: bounds.start });
      res.status(201).json(saved);
      return;
    }

    const task = new Task({
      user: userId,
      title,
      description,
      type,
      priority,
      dueDate: dueDate ? new Date(dueDate) : undefined,
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
    const { user, ...updates } = req.body;
    const userId = user?.id || '60d0fe4f5311236168a109ca';
    
    // Check if we are completing a task
    if (updates.status === 'completed') {
        const task = await Task.findById(id);
        if (task && task.series) {
          const series = await TaskSeries.findById(task.series);
          if (series) {
            const recurrence = series.recurrence as Recurrence;
            const intervalStart = task.intervalStart || getIntervalBounds(recurrence, task.dueDate || new Date()).start;
            const next = getNextIntervalBounds(recurrence, intervalStart);
            await ensureSeriesInstance(userId, series as any, next.start, new Date());
            series.lastGeneratedIntervalStart = next.start;
            await series.save();
          }
        }
        updates.completedAt = new Date();
    }

    if (updates.status === 'missed' && !updates.missedAt) {
      updates.missedAt = new Date();
    }

    const updateDoc: Record<string, unknown> = { $set: updates };

    if (updates.status && updates.status !== 'completed') {
      updateDoc.$unset = { ...(updateDoc.$unset as object), completedAt: 1 };
    }

    if (updates.status && updates.status !== 'missed') {
      updateDoc.$unset = { ...(updateDoc.$unset as object), missedAt: 1 };
    }

    const updatedTask = await Task.findByIdAndUpdate(id, updateDoc, { new: true });
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
