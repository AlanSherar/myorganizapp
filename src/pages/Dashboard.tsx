import React from 'react';
import { useTasks } from '../context/TaskContext';
import { useFinance } from '../context/FinanceContext';
import { useRoutines } from '../context/RoutineContext';
import { CheckCircle, DollarSign, Activity } from 'lucide-react';
import { PieChart, Pie, Cell, ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Tooltip } from 'recharts';

const Dashboard: React.FC = () => {
  const { tasks } = useTasks();
  const { transactions } = useFinance();
  const { disciplineScore } = useRoutines();

  // Task Completion Rate
  const completedTasks = tasks.filter(t => t.status === 'completed').length;
  const totalTasks = tasks.length;
  const taskRate = totalTasks > 0 ? (completedTasks / totalTasks) * 100 : 0;

  // Monthly Balance
  const currentMonth = new Date().getMonth();
  const monthlyTransactions = transactions.filter(t => new Date(t.date).getMonth() === currentMonth);
  const income = monthlyTransactions.filter(t => t.type === 'income').reduce((acc, t) => acc + t.amount, 0);
  const expense = monthlyTransactions.filter(t => t.type === 'expense').reduce((acc, t) => acc + t.amount, 0);
  const balance = income - expense;

  const taskData = [
    { name: 'Completed', value: completedTasks, color: '#10B981' },
    { name: 'Pending', value: totalTasks - completedTasks, color: '#64748B' },
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">Dashboard</h1>
      
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Task Summary */}
        <div className="app-card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-slate-700 dark:text-slate-200">Task Completion</h2>
            <CheckCircle className="text-emerald-600 dark:text-emerald-400" />
          </div>
          <div className="text-3xl font-bold text-slate-900 dark:text-slate-100">{Math.round(taskRate)}%</div>
          <div className="h-32 mt-4">
             <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                    <Pie data={taskData} dataKey="value" innerRadius={40} outerRadius={60} paddingAngle={5}>
                        {taskData.map((entry, index) => (
                            <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                    </Pie>
                </PieChart>
             </ResponsiveContainer>
          </div>
        </div>

        {/* Finance Summary */}
        <div className="app-card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-slate-700 dark:text-slate-200">Monthly Balance</h2>
            <DollarSign className="text-indigo-500 dark:text-indigo-300" />
          </div>
          <div className={`text-3xl font-bold ${balance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}>
            ${balance.toFixed(2)}
          </div>
          <div className="mt-2 text-sm text-slate-500 dark:text-slate-400">
            Income: <span className="text-emerald-600 dark:text-emerald-400">${income}</span> • Expense: <span className="text-rose-600 dark:text-rose-400">${expense}</span>
          </div>
        </div>

        {/* Discipline Score */}
        <div className="app-card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-slate-700 dark:text-slate-200">Discipline Score</h2>
            <Activity className="text-indigo-500 dark:text-indigo-300" />
          </div>
          <div className="text-3xl font-bold text-indigo-600 dark:text-indigo-300">{Math.round(disciplineScore)}%</div>
          <div className="mt-4 h-2.5 w-full rounded-full bg-stone-200 dark:bg-slate-800">
            <div className="h-2.5 rounded-full bg-indigo-500 dark:bg-indigo-400" style={{ width: `${disciplineScore}%` }}></div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
