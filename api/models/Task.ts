import mongoose, { Document, Schema } from 'mongoose';

export interface ITask extends Document {
  user: mongoose.Types.ObjectId;
  series?: mongoose.Types.ObjectId;
  title: string;
  description?: string;
  type: 'one-time' | 'recurring';
  recurrence?: 'daily' | 'weekly' | 'monthly' | 'yearly';
  status: 'pending' | 'completed' | 'missed';
  priority: 'low' | 'medium' | 'high';
  dueDate?: Date;
  intervalStart?: Date;
  intervalEnd?: Date;
  seriesId?: string;
  keepIfMissed?: boolean;
  completedAt?: Date;
  missedAt?: Date;
}

const TaskSchema: Schema = new Schema({
  user: { type: Schema.Types.ObjectId, ref: 'User', required: true },
  series: { type: Schema.Types.ObjectId, ref: 'TaskSeries' },
  title: { type: String, required: true },
  description: { type: String },
  type: { type: String, enum: ['one-time', 'recurring'], default: 'one-time' },
  recurrence: { type: String, enum: ['daily', 'weekly', 'monthly', 'yearly'] },
  status: { type: String, enum: ['pending', 'completed', 'missed'], default: 'pending' },
  priority: { type: String, enum: ['low', 'medium', 'high'], default: 'medium' },
  dueDate: { type: Date },
  intervalStart: { type: Date },
  intervalEnd: { type: Date },
  seriesId: { type: String, index: true },
  keepIfMissed: { type: Boolean, default: false },
  completedAt: { type: Date },
  missedAt: { type: Date },
}, { timestamps: true });

export default mongoose.model<ITask>('Task', TaskSchema);
