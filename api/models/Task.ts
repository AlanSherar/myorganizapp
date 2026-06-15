import mongoose, { Document, Schema } from 'mongoose';

export interface ITask extends Document {
  user: mongoose.Types.ObjectId;
  title: string;
  description?: string;
  type: 'one-time' | 'recurring';
  recurrence?: 'daily' | 'weekly' | 'monthly' | 'yearly';
  status: 'pending' | 'completed';
  priority: 'low' | 'medium' | 'high';
  dueDate?: Date;
  completedAt?: Date;
}

const TaskSchema: Schema = new Schema({
  user: { type: Schema.Types.ObjectId, ref: 'User', required: true },
  title: { type: String, required: true },
  description: { type: String },
  type: { type: String, enum: ['one-time', 'recurring'], default: 'one-time' },
  recurrence: { type: String, enum: ['daily', 'weekly', 'monthly', 'yearly'] },
  status: { type: String, enum: ['pending', 'completed'], default: 'pending' },
  priority: { type: String, enum: ['low', 'medium', 'high'], default: 'medium' },
  dueDate: { type: Date },
  completedAt: { type: Date },
}, { timestamps: true });

export default mongoose.model<ITask>('Task', TaskSchema);
