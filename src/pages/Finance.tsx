import React, { useState } from 'react';
import { useFinance, Transaction } from '../context/FinanceContext';
import { Plus, Trash2, Upload } from 'lucide-react';

const Finance: React.FC = () => {
  const { transactions, addTransaction, deleteTransaction, uploadStatement } = useFinance();
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [isUploadOpen, setIsUploadOpen] = useState(false);
  const [formData, setFormData] = useState<Partial<Transaction>>({
    amount: 0,
    category: '',
    sourceBank: 'Manual',
    type: 'expense',
    date: new Date().toISOString().split('T')[0]
  });
  const [uploadData, setUploadData] = useState({ source: 'bbva', data: '' });

  const handleAddSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.amount) return;
    await addTransaction(formData as any);
    setIsFormOpen(false);
    setFormData({ amount: 0, category: '', sourceBank: 'Manual', type: 'expense', date: new Date().toISOString().split('T')[0] });
  };

  const handleUploadSubmit = async (e: React.FormEvent) => {
      e.preventDefault();
      await uploadStatement(uploadData.source, uploadData.data);
      setIsUploadOpen(false);
      setUploadData({ source: 'bbva', data: '' });
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">Finance</h1>
        <div className="flex gap-2">
            <button
            onClick={() => setIsUploadOpen(!isUploadOpen)}
            className="btn-success"
            >
            <Upload size={20} />
            Import
            </button>
            <button
            onClick={() => setIsFormOpen(!isFormOpen)}
            className="btn-primary"
            >
            <Plus size={20} />
            Add
            </button>
        </div>
      </div>

      {isFormOpen && (
        <form onSubmit={handleAddSubmit} className="app-card space-y-4">
          <div className="grid grid-cols-2 gap-4">
             <div>
                <label className="app-label">Amount</label>
                <input
                    type="number"
                    value={formData.amount}
                    onChange={e => setFormData({ ...formData, amount: parseFloat(e.target.value) })}
                    className="app-input"
                    required
                />
             </div>
             <div>
                <label className="app-label">Type</label>
                <select
                    value={formData.type}
                    onChange={e => setFormData({ ...formData, type: e.target.value as any })}
                    className="app-input"
                >
                    <option value="expense">Expense</option>
                    <option value="income">Income</option>
                </select>
             </div>
             <div>
                <label className="app-label">Category</label>
                <input
                    type="text"
                    value={formData.category}
                    onChange={e => setFormData({ ...formData, category: e.target.value })}
                    className="app-input"
                    required
                />
             </div>
             <div>
                <label className="app-label">Source</label>
                <input
                    type="text"
                    value={formData.sourceBank}
                    onChange={e => setFormData({ ...formData, sourceBank: e.target.value })}
                    className="app-input"
                />
             </div>
             <div>
                <label className="app-label">Date</label>
                <input
                    type="date"
                    value={formData.date}
                    onChange={e => setFormData({ ...formData, date: e.target.value })}
                    className="app-input"
                />
             </div>
          </div>
          <button type="submit" className="btn-primary w-full">Save Transaction</button>
        </form>
      )}

      {isUploadOpen && (
          <form onSubmit={handleUploadSubmit} className="app-card space-y-4">
              <div>
                <label className="app-label">Source</label>
                <select
                    value={uploadData.source}
                    onChange={e => setUploadData({ ...uploadData, source: e.target.value })}
                    className="app-input"
                >
                    <option value="bbva">BBVA (CSV: Date,Desc,Amount)</option>
                    <option value="nacion">Banco Nación (CSV: Date;Desc;Amount)</option>
                    <option value="mercadopago">MercadoPago (JSON)</option>
                </select>
              </div>
              <div>
                <label className="app-label">Data (Paste Content)</label>
                <textarea
                    value={uploadData.data}
                    onChange={e => setUploadData({ ...uploadData, data: e.target.value })}
                    className="app-input h-32"
                    placeholder="Paste your CSV or JSON content here..."
                />
              </div>
              <button type="submit" className="btn-success w-full">Import Data</button>
          </form>
      )}

      <div className="table-shell overflow-x-auto">
        <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
            <thead className="table-head">
                <tr>
                    <th className="px-6 py-3">Date</th>
                    <th className="px-6 py-3">Description</th>
                    <th className="px-6 py-3">Category</th>
                    <th className="px-6 py-3">Source</th>
                    <th className="px-6 py-3">Amount</th>
                    <th className="px-6 py-3">Action</th>
                </tr>
            </thead>
            <tbody>
                {transactions.map(tx => (
                    <tr key={tx._id} className="table-row">
                        <td className="px-6 py-4">{new Date(tx.date).toLocaleDateString()}</td>
                        <td className="px-6 py-4">{tx.description || '-'}</td>
                        <td className="px-6 py-4">{tx.category}</td>
                        <td className="px-6 py-4">{tx.sourceBank}</td>
                        <td className={`px-6 py-4 font-medium ${tx.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}>
                            {tx.type === 'income' ? '+' : '-'}${tx.amount}
                        </td>
                        <td className="px-6 py-4">
                            <button onClick={() => deleteTransaction(tx._id)} className="text-rose-500 transition-colors hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300">
                                <Trash2 size={16} />
                            </button>
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
      </div>
    </div>
  );
};

export default Finance;
