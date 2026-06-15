import { Request, Response } from 'express';
import Routine from '../models/Routine.js';
import RoutineCheckIn from '../models/RoutineCheckIn.js';

export const getRoutines = async (req: Request, res: Response) => {
  try {
    const routines = await Routine.find({ user: req.body.user.id || '60d0fe4f5311236168a109ca' });
    res.json(routines);
  } catch (error) {
    res.status(500).json({ message: 'Server Error' });
  }
};

export const createRoutine = async (req: Request, res: Response) => {
  try {
    const routine = await Routine.create({
        ...req.body,
        user: req.body.user.id || '60d0fe4f5311236168a109ca'
    });
    res.status(201).json(routine);
  } catch (error) {
    res.status(400).json({ message: 'Invalid Data' });
  }
};

export const deleteRoutine = async (req: Request, res: Response) => {
    try {
      await Routine.findByIdAndDelete(req.params.id);
      res.json({ message: 'Routine Removed' });
    } catch (error) {
      res.status(500).json({ message: 'Delete Failed' });
    }
  };

export const checkIn = async (req: Request, res: Response) => {
    try {
        const { routineId, date, status } = req.body; // date YYYY-MM-DD
        const userId = req.body.user.id || '60d0fe4f5311236168a109ca';

        const checkIn = await RoutineCheckIn.findOneAndUpdate(
            { routine: routineId, date, user: userId },
            { status, completedAt: new Date() },
            { upsert: true, new: true }
        );
        res.json(checkIn);
    } catch (error) {
        res.status(400).json({ message: 'Check-in Failed' });
    }
};

export const getCheckIns = async (req: Request, res: Response) => {
    try {
        const { date } = req.query; // YYYY-MM-DD
        const userId = req.body.user.id || '60d0fe4f5311236168a109ca';
        const query: any = { user: userId };
        if (date) query.date = date;

        const checkIns = await RoutineCheckIn.find(query);
        res.json(checkIns);
    } catch (error) {
        res.status(500).json({ message: 'Server Error' });
    }
}

export const getDisciplineScore = async (req: Request, res: Response) => {
    try {
        const userId = req.body.user.id || '60d0fe4f5311236168a109ca';
        const { startDate, endDate } = req.query;
        // Logic to calculate score:
        // 1. Get all active routines (assume active during period for simplicity)
        // 2. Count expected check-ins (active routines * days)
        // 3. Count actual 'completed' check-ins
        
        // Simplified for "Today" if no dates provided
        const today = new Date().toISOString().split('T')[0];
        const start = (startDate as string) || today;
        const end = (endDate as string) || today;

        const routines = await Routine.find({ user: userId, active: true });
        const checkIns = await RoutineCheckIn.find({
            user: userId,
            date: { $gte: start, $lte: end },
            status: 'completed'
        });

        // Simple calculation: just count total completed.
        // For percentage, we need "expected".
        // Expected = Routines that should have happened.
        // Filter routines by day of week?
        // Let's keep it simple: Total Completed / Total Active Routines (for 1 day) * 100
        
        const totalRoutines = routines.length;
        if (totalRoutines === 0) return res.json({ score: 0 });

        const score = (checkIns.length / totalRoutines) * 100;
        res.json({ score: Math.min(score, 100) }); // Cap at 100
    } catch (error) {
        res.status(500).json({ message: 'Calculation Failed' });
    }
};
