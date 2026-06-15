import { Request, Response } from 'express';
import Transaction from '../models/Transaction.js';

export const getTransactions = async (req: Request, res: Response) => {
  try {
    const transactions = await Transaction.find({ user: req.body.user.id || '60d0fe4f5311236168a109ca' }).sort({ date: -1 });
    res.json(transactions);
  } catch (error) {
    res.status(500).json({ message: 'Server Error' });
  }
};

export const addTransaction = async (req: Request, res: Response) => {
  try {
    const transaction = await Transaction.create({
        ...req.body,
        user: req.body.user.id || '60d0fe4f5311236168a109ca'
    });
    res.status(201).json(transaction);
  } catch (error) {
    res.status(400).json({ message: 'Invalid Data' });
  }
};

export const deleteTransaction = async (req: Request, res: Response) => {
    try {
      await Transaction.findByIdAndDelete(req.params.id);
      res.json({ message: 'Transaction Removed' });
    } catch (error) {
      res.status(500).json({ message: 'Delete Failed' });
    }
  };

// Mock Parser Service
export const uploadStatement = async (req: Request, res: Response) => {
    const { source, data } = req.body; // data is the raw string/json content
    const userId = req.body.user.id || '60d0fe4f5311236168a109ca';
    
    let transactions: any[] = [];

    try {
        if (source === 'bbva') {
            // Mock CSV: Date,Description,Amount (Income positive, Expense negative)
            // Example: "2023-10-01,Salary,1000\n2023-10-02,Rent,-500"
            const lines = (data as string).split('\n');
            lines.forEach(line => {
                const [date, desc, amountStr] = line.split(',');
                if (!date || !amountStr) return;
                const amount = parseFloat(amountStr);
                transactions.push({
                    user: userId,
                    date: new Date(date),
                    category: 'Uncategorized',
                    sourceBank: 'BBVA',
                    type: amount >= 0 ? 'income' : 'expense',
                    amount: Math.abs(amount),
                    description: desc
                });
            });
        } else if (source === 'mercadopago') {
            // Mock JSON
            const parsed = typeof data === 'string' ? JSON.parse(data) : data;
            parsed.forEach((item: any) => {
                transactions.push({
                    user: userId,
                    date: new Date(item.date_created),
                    category: 'Uncategorized',
                    sourceBank: 'MercadoPago',
                    type: item.type === 'money_in' ? 'income' : 'expense',
                    amount: item.transaction_amount,
                    description: item.description
                });
            });
        } else if (source === 'nacion') {
            // Mock CSV: Date;Concept;Amount
            const lines = (data as string).split('\n');
            lines.forEach(line => {
                const [date, desc, amountStr] = line.split(';');
                if (!date || !amountStr) return;
                const amount = parseFloat(amountStr);
                transactions.push({
                    user: userId,
                    date: new Date(date),
                    category: 'Uncategorized',
                    sourceBank: 'Banco Nación',
                    type: amount >= 0 ? 'income' : 'expense',
                    amount: Math.abs(amount),
                    description: desc
                });
            });
        }

        if (transactions.length > 0) {
            await Transaction.insertMany(transactions);
        }

        res.status(201).json({ message: `Imported ${transactions.length} transactions`, transactions });
    } catch (error) {
        console.error(error);
        res.status(400).json({ message: 'Parsing Failed' });
    }
};
