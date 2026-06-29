import mongoose, { Document, Schema } from 'mongoose';

export type TaskRecurrence = 'daily' | 'weekly' | 'monthly' | 'yearly';

export interface TaskSeriesDueRule {
  time: string;
  dayOfWeek?: number;
  dayOfMonth?: number;
  month?: number;
}

export interface ITaskSeries extends Document {
  user: mongoose.Types.ObjectId;
  title: string;
  description?: string;
  recurrence: TaskRecurrence;
  priority: 'low' | 'medium' | 'high';
  keepIfMissed: boolean;
  dueRule: TaskSeriesDueRule;
  startAt: Date;
  stopAt?: Date;
  lastGeneratedIntervalStart?: Date;
  legacySeriesId?: string;
}

const TaskSeriesSchema: Schema = new Schema(
  {
    user: { type: Schema.Types.ObjectId, ref: 'User', required: true },
    title: { type: String, required: true },
    description: { type: String },
    recurrence: { type: String, enum: ['daily', 'weekly', 'monthly', 'yearly'], required: true },
    priority: { type: String, enum: ['low', 'medium', 'high'], default: 'medium' },
    keepIfMissed: { type: Boolean, default: false },
    dueRule: {
      time: { type: String, required: true },
      dayOfWeek: { type: Number },
      dayOfMonth: { type: Number },
      month: { type: Number },
    },
    startAt: { type: Date, required: true },
    stopAt: { type: Date },
    lastGeneratedIntervalStart: { type: Date },
    legacySeriesId: { type: String, index: true },
  },
  { timestamps: true },
);

TaskSeriesSchema.index({ user: 1, recurrence: 1 });

export default mongoose.model<ITaskSeries>('TaskSeries', TaskSeriesSchema);
