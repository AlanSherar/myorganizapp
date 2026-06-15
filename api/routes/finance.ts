import { Router } from 'express';
import { getTransactions, addTransaction, deleteTransaction, uploadStatement } from '../controllers/financeController.js';

const router = Router();

router.get('/', getTransactions);
router.post('/', addTransaction);
router.delete('/:id', deleteTransaction);
router.post('/upload', uploadStatement);

export default router;
