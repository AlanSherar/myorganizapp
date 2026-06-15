import { Router } from 'express';
import { getRoutines, createRoutine, deleteRoutine, checkIn, getCheckIns, getDisciplineScore } from '../controllers/routineController.js';

const router = Router();

router.get('/', getRoutines);
router.post('/', createRoutine);
router.delete('/:id', deleteRoutine);
router.post('/checkin', checkIn);
router.get('/checkins', getCheckIns);
router.get('/score', getDisciplineScore);

export default router;
