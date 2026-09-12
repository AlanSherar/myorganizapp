import { Request, Response } from 'express'
import { services } from '../services/index.js'
import type { StatementSource } from '../services/financeService.js'

const DEFAULT_USER_ID = '60d0fe4f5311236168a109ca'

export const getTransactions = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const transactions = await services.finance.getTransactions(userId)
    res.json(transactions)
  } catch (error) {
    console.error('[financeController.getTransactions]', error)
    res.status(500).json({ message: 'Server Error' })
  }
}

export const addTransaction = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const { amount, date, category, sourceBank, type, description } = req.body
    const transaction = await services.finance.addTransaction(userId, {
      amount,
      date,
      category,
      sourceBank,
      type,
      description,
    })
    res.status(201).json(transaction)
  } catch (error) {
    console.error('[financeController.addTransaction]', error)
    res.status(400).json({ message: 'Invalid Data' })
  }
}

export const deleteTransaction = async (req: Request, res: Response) => {
  try {
    await services.finance.deleteTransaction(req.params.id)
    res.json({ message: 'Transaction Removed' })
  } catch (error) {
    console.error('[financeController.deleteTransaction]', error)
    res.status(500).json({ message: 'Delete Failed' })
  }
}

export const uploadStatement = async (req: Request, res: Response) => {
  try {
    const userId = (req.body.user?.id as string) || DEFAULT_USER_ID
    const { source, data }: { source: StatementSource; data: string | unknown } = req.body
    const result = await services.finance.uploadStatement(userId, source, data)
    res
      .status(201)
      .json({ message: `Imported ${result.imported} transactions`, transactions: result.transactions })
  } catch (error) {
    console.error('[financeController.uploadStatement]', error)
    res.status(400).json({ message: 'Parsing Failed' })
  }
}
