import React, { useState } from 'react';
import { useTasks, Task } from '../context/TaskContext';
import { Plus, Trash2, CheckCircle, Circle, Repeat, RotateCcw } from 'lucide-react';

const Tasks: React.FC = () => {
  const { tasks, addTask, updateTask, deleteTask } = useTasks();
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [activeTab, setActiveTab] = useState<'todo' | 'completed' | 'missed'>('todo');
  const [now, setNow] = useState(() => Date.now());
  const [dueDateDate, setDueDateDate] = useState('');
  const [dueDateTime, setDueDateTime] = useState('23:59');
  const [formData, setFormData] = useState<Partial<Task>>({
    title: '',
    type: 'one-time',
    priority: 'medium',
    status: 'pending'
  });
  const [pendingCompletion, setPendingCompletion] = useState<
    Record<string, { timeoutId: number; startedAt: number }>
  >({});
  const pendingCompletionRef = React.useRef<Record<string, { timeoutId: number; startedAt: number }>>({});
  const [cancelingCompletion, setCancelingCompletion] = useState<Record<string, boolean>>({});
  const cancelingTimeoutRef = React.useRef<Record<string, number>>({});
  const [selectedCompleted, setSelectedCompleted] = useState<Record<string, boolean>>({});
  const [isDeleteConfirmOpen, setIsDeleteConfirmOpen] = useState(false);
  const [isBulkDeleting, setIsBulkDeleting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.title) return;
    const payload: any = { ...formData };
    if (dueDateDate) {
      const dateTime = new Date(`${dueDateDate}T${dueDateTime}:00`);
      payload.dueDate = dateTime.toISOString();
    } else {
      delete payload.dueDate;
    }
    await addTask(payload);
    setIsFormOpen(false);
    setFormData({ title: '', type: 'one-time', priority: 'medium', status: 'pending' });
    setDueDateDate('');
    setDueDateTime('23:59');
  };

  const COMPLETION_DELAY_MS = 4599;
  const CANCEL_RESET_MS = 250;

  React.useEffect(() => {
    const hasPending = Object.keys(pendingCompletion).length > 0;
    if (!hasPending) return;
    const intervalId = window.setInterval(() => setNow(Date.now()), 250);
    return () => window.clearInterval(intervalId);
  }, [pendingCompletion]);

  React.useEffect(() => {
    pendingCompletionRef.current = pendingCompletion;
  }, [pendingCompletion]);

  React.useEffect(() => {
    return () => {
      Object.values(pendingCompletionRef.current).forEach((p) => window.clearTimeout(p.timeoutId));
      Object.values(cancelingTimeoutRef.current).forEach((t) => window.clearTimeout(t));
    };
  }, []);

  const triggerResetAnim = (taskId: string) => {
    const existing = cancelingTimeoutRef.current[taskId];
    if (existing) window.clearTimeout(existing);

    setCancelingCompletion((prev) => ({
      ...prev,
      [taskId]: true,
    }));

    cancelingTimeoutRef.current[taskId] = window.setTimeout(() => {
      setCancelingCompletion((prev) => {
        const { [taskId]: _, ...rest } = prev;
        return rest;
      });
      delete cancelingTimeoutRef.current[taskId];
    }, CANCEL_RESET_MS);
  };

  const startCompletion = (taskId: string) => {
    if (pendingCompletion[taskId]) return;

    const startedAt = Date.now();
    setNow(startedAt);
    const timeoutId = window.setTimeout(async () => {
      try {
        await updateTask(taskId, { status: 'completed' });
      } catch {
        triggerResetAnim(taskId);
      } finally {
        setPendingCompletion((prev) => {
          const { [taskId]: _, ...rest } = prev;
          return rest;
        });
      }
    }, COMPLETION_DELAY_MS);

    setPendingCompletion((prev) => ({
      ...prev,
      [taskId]: { timeoutId, startedAt },
    }));
  };

  const cancelCompletion = (taskId: string) => {
    const entry = pendingCompletion[taskId];
    if (!entry) return;
    window.clearTimeout(entry.timeoutId);
    triggerResetAnim(taskId);
    setPendingCompletion((prev) => {
      const { [taskId]: _, ...rest } = prev;
      return rest;
    });
  };

  const handleCircleClick = (task: Task) => {
    if (task.status !== 'pending') return;
    if (pendingCompletion[task._id]) {
      cancelCompletion(task._id);
      return;
    }
    startCompletion(task._id);
  };

  const getPriorityClass = (priority: Task['priority']) => {
    if (priority === 'high') return 'badge-base badge-high';
    if (priority === 'medium') return 'badge-base badge-medium';
    return 'badge-base badge-low';
  };

  const pendingTasks = React.useMemo(() => tasks.filter((t) => t.status === 'pending'), [tasks]);
  const completedTasks = React.useMemo(() => tasks.filter((t) => t.status === 'completed'), [tasks]);
  const missedTasks = React.useMemo(() => tasks.filter((t) => t.status === 'missed'), [tasks]);
  const selectedCompletedIds = Object.keys(selectedCompleted).filter((id) => selectedCompleted[id]);

  React.useEffect(() => {
    const completedIds = new Set(completedTasks.map((t) => t._id));
    setSelectedCompleted((prev) => {
      const next: Record<string, boolean> = {};
      for (const [id, checked] of Object.entries(prev)) {
        if (completedIds.has(id) && checked) next[id] = true;
      }
      return next;
    });
  }, [completedTasks]);

  const toggleCompletedSelection = (taskId: string) => {
    setSelectedCompleted((prev) => ({
      ...prev,
      [taskId]: !prev[taskId],
    }));
  };

  const setAllCompletedSelection = (checked: boolean) => {
    if (!checked) {
      setSelectedCompleted({});
      return;
    }
    const next: Record<string, boolean> = {};
    completedTasks.forEach((t) => {
      next[t._id] = true;
    });
    setSelectedCompleted(next);
  };

  const confirmBulkDelete = () => {
    if (selectedCompletedIds.length === 0) return;
    setIsDeleteConfirmOpen(true);
  };

  const runBulkDelete = async () => {
    if (selectedCompletedIds.length === 0) return;
    setIsBulkDeleting(true);
    try {
      await Promise.all(selectedCompletedIds.map((id) => deleteTask(id)));
      setSelectedCompleted({});
      setIsDeleteConfirmOpen(false);
    } finally {
      setIsBulkDeleting(false);
    }
  };

  const revertCompletedTask = async (task: Task) => {
    const nowDate = new Date();
    const due = task.dueDate ? new Date(task.dueDate) : null;
    const targetStatus: Task['status'] = due && due.getTime() < nowDate.getTime() ? 'missed' : 'pending';

    await updateTask(task._id, { status: targetStatus });
    setSelectedCompleted((prev) => {
      const { [task._id]: _, ...rest } = prev;
      return rest;
    });
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

      <div className="app-card p-2">
        <div className="flex flex-wrap gap-2">
          <button
            className={`rounded-xl px-4 py-2 text-sm font-medium transition ${
              activeTab === 'todo'
                ? 'bg-indigo-600 text-white'
                : 'text-slate-600 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800'
            }`}
            onClick={() => setActiveTab('todo')}
          >
            Por hacer ({pendingTasks.length})
          </button>
          <button
            className={`rounded-xl px-4 py-2 text-sm font-medium transition ${
              activeTab === 'completed'
                ? 'bg-indigo-600 text-white'
                : 'text-slate-600 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800'
            }`}
            onClick={() => setActiveTab('completed')}
          >
            Completadas ({completedTasks.length})
          </button>
          <button
            className={`rounded-xl px-4 py-2 text-sm font-medium transition ${
              activeTab === 'missed'
                ? 'bg-indigo-600 text-white'
                : 'text-slate-600 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800'
            }`}
            onClick={() => setActiveTab('missed')}
          >
            No completadas ({missedTasks.length})
          </button>
        </div>
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
            <div className="col-span-2">
              <label className="app-label">Due Date</label>
              <div className="mt-1 flex gap-2">
                <input
                  type="date"
                  value={dueDateDate}
                  onChange={e => setDueDateDate(e.target.value)}
                  className="app-input"
                />
                <input
                  type="time"
                  value={dueDateTime}
                  onChange={(e) => setDueDateTime(e.target.value)}
                  className="app-input"
                />
              </div>
            </div>
          </div>
          {formData.type === 'recurring' && (
            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input
                type="checkbox"
                checked={!!formData.keepIfMissed}
                onChange={(e) => setFormData({ ...formData, keepIfMissed: e.target.checked })}
                className="h-4 w-4 rounded border-stone-300 text-indigo-600 focus:ring-indigo-400/30 dark:border-slate-700 dark:bg-slate-800"
              />
              Mantener si no completadas
            </label>
          )}
          <button type="submit" className="btn-primary w-full">Save Task</button>
        </form>
      )}

      {activeTab === 'todo' && (
        <div className="space-y-3">
          {pendingTasks.length === 0 ? (
            <div className="app-card p-4 text-sm text-slate-500 dark:text-slate-400">No hay tareas por hacer.</div>
          ) : (
            pendingTasks.map((task) => {
              const pending = pendingCompletion[task._id];
              const isCanceling = !!cancelingCompletion[task._id];
              const durationMs = pending ? COMPLETION_DELAY_MS : isCanceling ? CANCEL_RESET_MS : 0;
              const elapsedMs = pending ? Math.max(0, now - pending.startedAt) : 0;
              const remainingSeconds = pending
                ? Math.max(1, Math.ceil((COMPLETION_DELAY_MS - elapsedMs) / 1000))
                : null;

              return (
                <div key={task._id} className="app-card relative overflow-hidden p-4">
                  <div
                    className="absolute inset-y-0 left-0 bg-emerald-500/10 transition-[width] ease-linear dark:bg-emerald-400/10"
                    style={{ width: pending ? '100%' : '0%', transitionDuration: `${durationMs}ms` }}
                  />

                  <div className="relative flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <button
                        onClick={() => handleCircleClick(task)}
                        className={`transition-colors ${
                          pending
                            ? 'text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300'
                            : 'text-slate-400 hover:text-indigo-600 dark:text-slate-500 dark:hover:text-indigo-300'
                        }`}
                        aria-label={pending ? 'Cancelar completar tarea' : 'Completar tarea'}
                      >
                        {pending ? <CheckCircle /> : <Circle />}
                      </button>

                      <div>
                        <h3 className="font-medium text-slate-900 dark:text-slate-100">{task.title}</h3>
                        <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                          <span className={getPriorityClass(task.priority)}>{task.priority}</span>
                          {task.type === 'recurring' && (
                            <span className="badge-base badge-recurring">
                              <Repeat size={12} /> {task.recurrence}
                            </span>
                          )}
                          {task.keepIfMissed && <span className="badge-base bg-stone-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300">mantener</span>}
                          {task.dueDate && <span>Due: {new Date(task.dueDate).toLocaleString()}</span>}
                          {pending && <span>Completando en {remainingSeconds}s (click para cancelar)</span>}
                        </div>
                      </div>
                    </div>

                    <button
                      onClick={() => deleteTask(task._id)}
                      className="text-slate-400 transition-colors hover:text-rose-600 dark:text-slate-500 dark:hover:text-rose-300"
                      aria-label="Eliminar tarea"
                    >
                      <Trash2 size={18} />
                    </button>
                  </div>
                </div>
              );
            })
          )}
        </div>
      )}

      {activeTab === 'completed' && (
        <div className="space-y-3">
          <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div className="flex items-center justify-between md:justify-start md:gap-3">
              <div className="text-sm text-slate-500 dark:text-slate-400">Seleccioná tareas para borrarlas</div>
            </div>

            <div className="flex items-center justify-between gap-3 md:justify-end">
              <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input
                  type="checkbox"
                  checked={completedTasks.length > 0 && selectedCompletedIds.length === completedTasks.length}
                  onChange={(e) => setAllCompletedSelection(e.target.checked)}
                  className="h-4 w-4 rounded border-stone-300 text-indigo-600 focus:ring-indigo-400/30 dark:border-slate-700 dark:bg-slate-800"
                />
                Seleccionar todo
              </label>

              <button
                className="btn-soft-danger"
                onClick={confirmBulkDelete}
                disabled={selectedCompletedIds.length === 0 || isBulkDeleting}
              >
                Eliminar seleccionadas ({selectedCompletedIds.length})
              </button>
            </div>
          </div>

          {completedTasks.length === 0 ? (
            <div className="app-card p-4 text-sm text-slate-500 dark:text-slate-400">Todavía no completaste ninguna tarea.</div>
          ) : (
            completedTasks.map((task) => (
              <div key={task._id} className="app-card flex items-center justify-between p-4">
                <div className="flex items-center gap-3">
                  <input
                    type="checkbox"
                    checked={!!selectedCompleted[task._id]}
                    onChange={() => toggleCompletedSelection(task._id)}
                    className="h-4 w-4 rounded border-stone-300 text-indigo-600 focus:ring-indigo-400/30 dark:border-slate-700 dark:bg-slate-800"
                  />

                  <button
                    onClick={() => revertCompletedTask(task)}
                    className="text-slate-500 transition-colors hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-300"
                    aria-label="Revertir tarea"
                    title="Revertir"
                  >
                    <RotateCcw size={18} />
                  </button>

                  <div>
                    <h3 className="font-medium text-slate-600 dark:text-slate-300">{task.title}</h3>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                      <span className={getPriorityClass(task.priority)}>{task.priority}</span>
                      {task.type === 'recurring' && (
                        <span className="badge-base badge-recurring">
                          <Repeat size={12} /> {task.recurrence}
                        </span>
                      )}
                      {task.dueDate && <span>Due: {new Date(task.dueDate).toLocaleString()}</span>}
                    </div>
                  </div>
                </div>

                <button
                  onClick={() => deleteTask(task._id)}
                  className="text-slate-400 transition-colors hover:text-rose-600 dark:text-slate-500 dark:hover:text-rose-300"
                  aria-label="Eliminar tarea"
                >
                  <Trash2 size={18} />
                </button>
              </div>
            ))
          )}
        </div>
      )}

      {activeTab === 'missed' && (
        <div className="space-y-3">
          {missedTasks.length === 0 ? (
            <div className="app-card p-4 text-sm text-slate-500 dark:text-slate-400">No tenés tareas no completadas.</div>
          ) : (
            missedTasks.map((task) => (
              <div key={task._id} className="app-card flex items-center justify-between p-4">
                <div className="flex items-center gap-3">
                  <div className="h-8 w-1 rounded-full bg-rose-400/70 dark:bg-rose-400/50" aria-hidden="true" />
                  <div>
                    <h3 className="font-medium text-slate-900 dark:text-slate-100">{task.title}</h3>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                      <span className={getPriorityClass(task.priority)}>{task.priority}</span>
                      {task.type === 'recurring' && (
                        <span className="badge-base badge-recurring">
                          <Repeat size={12} /> {task.recurrence}
                        </span>
                      )}
                      {task.dueDate && <span>Venció: {new Date(task.dueDate).toLocaleString()}</span>}
                    </div>
                  </div>
                </div>

                <button
                  onClick={() => deleteTask(task._id)}
                  className="text-slate-400 transition-colors hover:text-rose-600 dark:text-slate-500 dark:hover:text-rose-300"
                  aria-label="Eliminar tarea"
                >
                  <Trash2 size={18} />
                </button>
              </div>
            ))
          )}
        </div>
      )}

      {isDeleteConfirmOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/40" onClick={() => setIsDeleteConfirmOpen(false)} />
          <div className="app-card relative w-full max-w-lg p-5">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Eliminar tareas</h3>
            <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
              Vas a eliminar {selectedCompletedIds.length} tarea(s). Esta acción no se puede deshacer.
            </p>
            <div className="mt-4 flex items-center justify-end gap-2">
              <button className="btn-secondary" onClick={() => setIsDeleteConfirmOpen(false)} disabled={isBulkDeleting}>
                Cancelar
              </button>
              <button className="btn-soft-danger" onClick={runBulkDelete} disabled={isBulkDeleting}>
                {isBulkDeleting ? 'Eliminando…' : 'Eliminar definitivamente'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Tasks;
