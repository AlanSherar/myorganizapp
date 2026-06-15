import mongoose, { Document, Schema } from 'mongoose';

export interface IRoutine extends Document {
  user: mongoose.Types.ObjectId;
  title: string;
  startTime: string; // HH:mm
  endTime: string; // HH:mm
  days: number[]; // 0=Sunday, 1=Monday, ...
  active: boolean;
}

const RoutineSchema: Schema = new Schema({
  user: { type: Schema.Types.ObjectId, ref: 'User', required: true },
  title: { type: String, required: true },
  startTime: { type: String, required: true },
  endTime: { type: String, required: true },
  days: [{ type: Number, min: 0, max: 6 }],
  active: { type: Boolean, default: true },
}, { timestamps: true });

export default mongoose.model<IRoutine>('Routine', RoutineSchema);
