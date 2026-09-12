import React, { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { financeService, type Transaction as ServiceTransaction, type CreateTransactionInput } from '../services'

export interface Transaction {
  _id: string
  amount: number
  date: string
  category: string
  sourceBank: string
  type: 'income' | 'expense'
  description?: string
}

interface FinanceContextType {
  transactions: Transaction[]
  fetchTransactions: () => Promise<void>
  addTransaction: (tx: Omit<Transaction, '_id'>) => Promise<void>
  deleteTransaction: (id: string) => Promise<void>
  uploadStatement: (source: string, data: string | unknown) => Promise<void>
}

const FinanceContext = createContext<FinanceContextType | undefined>(undefined)

function toContext(tx: ServiceTransaction): Transaction {
  return {
    _id: tx.id,
    amount: tx.amount,
    date: tx.date,
    category: tx.category,
    sourceBank: tx.sourceBank,
    type: tx.type,
    description: tx.description,
  }
}

export const FinanceProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [transactions, setTransactions] = useState<Transaction[]>([])

  const fetchTransactions = useCallback(async () => {
    try {
      const data = await financeService.getTransactions()
      setTransactions(data.map(toContext))
    } catch (error) {
      console.error('Failed to fetch transactions', error)
    }
  }, [])

  const addTransaction = async (tx: Omit<Transaction, '_id'>) => {
    try {
      const input: CreateTransactionInput = {
        amount: tx.amount,
        date: tx.date,
        category: tx.category,
        sourceBank: tx.sourceBank,
        type: tx.type,
        description: tx.description,
      }
      await financeService.addTransaction(input)
      await fetchTransactions()
    } catch (error) {
      console.error('Failed to add transaction', error)
    }
  }

  const deleteTransaction = async (id: string) => {
    try {
      await financeService.deleteTransaction(id)
      await fetchTransactions()
    } catch (error) {
      console.error('Failed to delete transaction', error)
    }
  }

  const uploadStatement = async (source: string, data: string | unknown) => {
    try {
      await financeService.uploadStatement(source, data)
      await fetchTransactions()
    } catch (error) {
      console.error('Failed to upload statement', error)
    }
  }

  useEffect(() => {
    fetchTransactions()
  }, [fetchTransactions])

  return (
    <FinanceContext.Provider
      value={{ transactions, fetchTransactions, addTransaction, deleteTransaction, uploadStatement }}
    >
      {children}
    </FinanceContext.Provider>
  )
}

export const useFinance = () => {
  const context = useContext(FinanceContext)
  if (!context) throw new Error('useFinance must be used within a FinanceProvider')
  return context
}
