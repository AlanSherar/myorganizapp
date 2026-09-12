import type { Repositories, Transaction, TransactionType } from '../repositories/types.js'

export type StatementSource = 'bbva' | 'mercadopago' | 'nacion'

export function createFinanceService(repos: Repositories) {
  const { transaction } = repos

  async function getTransactions(userId: string): Promise<Transaction[]> {
    return transaction.findByUserId(userId)
  }

  async function addTransaction(
    userId: string,
    data: {
      amount: number
      date: string | Date
      category: string
      sourceBank: string
      type: TransactionType
      description?: string
    },
  ): Promise<Transaction> {
    return transaction.create({
      userId,
      amount: data.amount,
      date: typeof data.date === 'string' ? new Date(data.date) : data.date,
      category: data.category,
      sourceBank: data.sourceBank,
      type: data.type,
      description: data.description,
    })
  }

  async function deleteTransaction(id: string): Promise<void> {
    await transaction.delete(id)
  }

  function parseBBVA(data: string, userId: string): Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> {
    const lines = data.split('\n')
    const result: Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> = []
    for (const line of lines) {
      const [date, desc, amountStr] = line.split(',')
      if (!date || !amountStr) continue
      const amount = parseFloat(amountStr)
      if (!Number.isFinite(amount)) continue
      result.push({
        userId,
        date: new Date(date),
        category: 'Uncategorized',
        sourceBank: 'BBVA',
        type: amount >= 0 ? 'income' : 'expense',
        amount: Math.abs(amount),
        description: desc?.trim() || undefined,
      })
    }
    return result
  }

  function parseMercadoPago(
    data: string | Array<{ date_created?: string; type?: string; transaction_amount?: number; description?: string }>,
    userId: string,
  ): Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> {
    const parsed: Array<{ date_created?: string; type?: string; transaction_amount?: number; description?: string }> =
      typeof data === 'string' ? JSON.parse(data) : data
    const result: Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> = []
    if (!Array.isArray(parsed)) return result

    for (const item of parsed) {
      if (!item.date_created || item.transaction_amount === undefined) continue
      result.push({
        userId,
        date: new Date(item.date_created),
        category: 'Uncategorized',
        sourceBank: 'MercadoPago',
        type: item.type === 'money_in' ? 'income' : 'expense',
        amount: item.transaction_amount,
        description: item.description,
      })
    }
    return result
  }

  function parseNacion(data: string, userId: string): Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> {
    const lines = data.split('\n')
    const result: Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> = []
    for (const line of lines) {
      const [date, desc, amountStr] = line.split(';')
      if (!date || !amountStr) continue
      const amount = parseFloat(amountStr)
      if (!Number.isFinite(amount)) continue
      result.push({
        userId,
        date: new Date(date),
        category: 'Uncategorized',
        sourceBank: 'Banco Nación',
        type: amount >= 0 ? 'income' : 'expense',
        amount: Math.abs(amount),
        description: desc?.trim() || undefined,
      })
    }
    return result
  }

  async function uploadStatement(
    userId: string,
    source: StatementSource,
    data: string | unknown,
  ): Promise<{ imported: number; transactions: Transaction[] }> {
    let transactions: Array<Omit<Transaction, 'id' | 'createdAt' | 'updatedAt'>> = []

    if (source === 'bbva') {
      transactions = parseBBVA(typeof data === 'string' ? data : String(data), userId)
    } else if (source === 'mercadopago') {
      const mpData: string | Array<{ date_created?: string; type?: string; transaction_amount?: number; description?: string }> =
        typeof data === 'string' || Array.isArray(data)
          ? (data as string | Array<{ date_created?: string; type?: string; transaction_amount?: number; description?: string }>)
          : typeof data === 'object' && data !== null
            ? [data as { date_created?: string; type?: string; transaction_amount?: number; description?: string }]
            : String(data)
      transactions = parseMercadoPago(mpData, userId)
    } else if (source === 'nacion') {
      transactions = parseNacion(typeof data === 'string' ? data : String(data), userId)
    }

    let created: Transaction[] = []
    if (transactions.length > 0) {
      created = await transaction.createMany(transactions)
    }

    return { imported: created.length, transactions: created }
  }

  return {
    getTransactions,
    addTransaction,
    deleteTransaction,
    uploadStatement,
  }
}
