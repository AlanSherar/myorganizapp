import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { api } from '../lib/api';

export interface Transaction {
  _id: string;
  amount: number;
  date: string;
  category: string;
  sourceBank: string;
  type: 'income' | 'expense';
  description?: string;
}

interface FinanceContextType {
  transactions: Transaction[];
  fetchTransactions: () => Promise<void>;
  addTransaction: (tx: Omit<Transaction, '_id'>) => Promise<void>;
  deleteTransaction: (id: string) => Promise<void>;
  uploadStatement: (source: string, data: string | any) => Promise<void>;
}

const FinanceContext = createContext<FinanceContextType | undefined>(undefined);

export const FinanceProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [transactions, setTransactions] = useState<Transaction[]>([]);

  const fetchTransactions = useCallback(async () => {
    try {
      const data = await api.get('/finance');
      setTransactions(data);
    } catch (error) {
      console.error('Failed to fetch transactions', error);
    }
  }, []);

  const addTransaction = async (tx: Omit<Transaction, '_id'>) => {
    await api.post('/finance', tx);
    fetchTransactions();
  };

  const deleteTransaction = async (id: string) => {
    await api.delete(`/finance/${id}`);
    fetchTransactions();
  };

  const uploadStatement = async (source: string, data: string | any) => {
    await api.post('/finance/upload', { source, data });
    fetchTransactions();
  };

  useEffect(() => {
    fetchTransactions();
  }, [fetchTransactions]);

  return (
    <FinanceContext.Provider value={{ transactions, fetchTransactions, addTransaction, deleteTransaction, uploadStatement }}>
      {children}
    </FinanceContext.Provider>
  );
};

export const useFinance = () => {
  const context = useContext(FinanceContext);
  if (!context) throw new Error('useFinance must be used within a FinanceProvider');
  return context;
};
