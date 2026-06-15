import React, { useState } from 'react';
import { useRoutines, Routine } from '../context/RoutineContext';
import { Plus, Trash2, Check, X } from 'lucide-react';

const Routines: React.FC = () => {
  const { routines, createRoutine, deleteRoutine, checkIn, disciplineScore } = useRoutines();
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [formData, setFormData] = useState<Partial<Routine>>({
    title: '',
    startTime: '09:00',
    endTime: '10:00',
    days: [0, 1, 2, 3, 4, 5, 6],
    active: true
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.title) return;
    await createRoutine(formData as any);
    setIsFormOpen(false);
    setFormData({ title: '', startTime: '09:00', endTime: '10:00', days: [0, 1, 2, 3, 4, 5, 6], active: true });
  };

  const handleCheckIn = async (routineId: string, status: 'completed' | 'missed') => {
      const today = new Date().toISOString().split('T')[0];
      await checkIn(routineId, status, today);
  };

  const daysMap = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
            <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">Routines</h1>
            <p className="text-sm text-slate-500 dark:text-slate-400">Discipline Score: {Math.round(disciplineScore)}%</p>
        </div>
        <button
          onClick={() => setIsFormOpen(!isFormOpen)}
          className="btn-primary"
        >
          <Plus size={20} />
          Add Routine
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
               <label className="app-label">Start Time</label>
               <input
                  type="time"
                  value={formData.startTime}
                  onChange={e => setFormData({ ...formData, startTime: e.target.value })}
                  className="app-input"
               />
            </div>
            <div>
               <label className="app-label">End Time</label>
               <input
                  type="time"
                  value={formData.endTime}
                  onChange={e => setFormData({ ...formData, endTime: e.target.value })}
                  className="app-input"
               />
            </div>
          </div>
          <button type="submit" className="btn-primary w-full">Save Routine</button>
        </form>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {routines.map(routine => (
          <div key={routine._id} className="app-card flex flex-col justify-between">
            <div>
                <div className="flex justify-between items-start">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{routine.title}</h3>
                    <button onClick={() => deleteRoutine(routine._id)} className="text-slate-400 transition-colors hover:text-rose-600 dark:text-slate-500 dark:hover:text-rose-300">
                        <Trash2 size={16} />
                    </button>
                </div>
                <div className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {routine.startTime} - {routine.endTime}
                </div>
                <div className="flex flex-wrap gap-1 mt-2">
                    {routine.days.map(d => (
                        <span key={d} className="badge-base bg-stone-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            {daysMap[d]}
                        </span>
                    ))}
                </div>
            </div>
            
            <div className="mt-6 flex gap-2">
                <button
                    onClick={() => handleCheckIn(routine._id, 'completed')}
                    className="btn-soft-success flex-1"
                >
                    <Check size={18} /> Done
                </button>
                <button
                    onClick={() => handleCheckIn(routine._id, 'missed')}
                    className="btn-soft-danger flex-1"
                >
                    <X size={18} /> Missed
                </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default Routines;
