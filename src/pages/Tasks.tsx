import React, { useState } from 'react';
import { useTasks, Task } from '../context/TaskContext';
import { Plus, Trash2, CheckCircle, Circle, Repeat } from 'lucide-react';

const Tasks: React.FC = () => {
  const { tasks, addTask, updateTask, deleteTask } = useTasks();
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [formData, setFormData] = useState<Partial<Task>>({
    title: '',
    type: 'one-time',
    priority: 'medium',
    status: 'pending'
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.title) return;
    await addTask(formData as any);
    setIsFormOpen(false);
    setFormData({ title: '', type: 'one-time', priority: 'medium', status: 'pending' });
  };

  const toggleStatus = (task: Task) => {
    const newStatus = task.status === 'completed' ? 'pending' : 'completed';
    updateTask(task._id, { status: newStatus });
  };

  const getPriorityClass = (priority: Task['priority']) => {
    if (priority === 'high') return 'badge-base badge-high';
    if (priority === 'medium') return 'badge-base badge-medium';
    return 'badge-base badge-low';
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">Tasks</h1>
        <button
          onClick={() => setIsFormOpen(!isFormOpen)}
          className="btn-primary"
        >
          <Plus size={20} />
          Add Task
        </button>
      </div>

      {isFormOpen && (
        <form onSubmit={handleSubmit} className="app-card space-y-4">
          <div>
            <label className="app-label">Title</label>
            <input
              type="text"
              value={formData.title}
              onChange={e => setFormData({ ...formData, title: e.target.value })}
              className="app-input"
              required
            />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="app-label">Type</label>
              <select
                value={formData.type}
                onChange={e => setFormData({ ...formData, type: e.target.value as any })}
                className="app-input"
              >
                <option value="one-time">One-time</option>
                <option value="recurring">Recurring</option>
              </select>
            </div>
            {formData.type === 'recurring' && (
              <div>
                <label className="app-label">Recurrence</label>
                <select
                  value={formData.recurrence}
                  onChange={e => setFormData({ ...formData, recurrence: e.target.value as any })}
                  className="app-input"
                >
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                  <option value="yearly">Yearly</option>
                </select>
              </div>
            )}
            <div>
               <label className="app-label">Priority</label>
               <select
                  value={formData.priority}
                  onChange={e => setFormData({ ...formData, priority: e.target.value as any })}
                  className="app-input"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
            </div>
            <div>
               <label className="app-label">Due Date</label>
               <input
                  type="date"
                  value={formData.dueDate ? new Date(formData.dueDate).toISOString().split('T')[0] : ''}
                  onChange={e => setFormData({ ...formData, dueDate: e.target.value })}
                  className="app-input"
               />
            </div>
          </div>
          <button type="submit" className="btn-primary w-full">Save Task</button>
        </form>
      )}

      <div className="space-y-3">
        {tasks.map(task => (
          <div key={task._id} className="app-card flex items-center justify-between p-4">
            <div className="flex items-center gap-3">
              <button onClick={() => toggleStatus(task)} className="text-slate-400 transition-colors hover:text-indigo-600 dark:text-slate-500 dark:hover:text-indigo-300">
                {task.status === 'completed' ? <CheckCircle className="text-emerald-600 dark:text-emerald-400" /> : <Circle />}
              </button>
              <div>
                <h3 className={`font-medium ${task.status === 'completed' ? 'text-slate-400 line-through dark:text-slate-500' : 'text-slate-900 dark:text-slate-100'}`}>
                  {task.title}
                </h3>
                <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                  <span className={getPriorityClass(task.priority)}>
                    {task.priority}
                  </span>
                  {task.type === 'recurring' && (
                    <span className="badge-base badge-recurring">
                      <Repeat size={12} /> {task.recurrence}
                    </span>
                  )}
                  {task.dueDate && <span>Due: {new Date(task.dueDate).toLocaleDateString()}</span>}
                </div>
              </div>
            </div>
            <button onClick={() => deleteTask(task._id)} className="text-slate-400 transition-colors hover:text-rose-600 dark:text-slate-500 dark:hover:text-rose-300">
              <Trash2 size={18} />
            </button>
          </div>
        ))}
      </div>
    </div>
  );
};

export default Tasks;
