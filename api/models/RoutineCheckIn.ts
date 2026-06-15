import mongoose, { Document, Schema } from 'mongoose';

export interface IRoutineCheckIn extends Document {
  user: mongoose.Types.ObjectId;
  routine: mongoose.Types.ObjectId;
  date: string; // YYYY-MM-DD
  status: 'completed' | 'missed'; // skipped?
  completedAt?: Date;
}

const RoutineCheckInSchema: Schema = new Schema({
  user: { type: Schema.Types.ObjectId, ref: 'User', required: true },
  routine: { type: Schema.Types.ObjectId, ref: 'Routine', required: true },
  date: { type: String, required: true }, // Store as string for easy aggregation by day
  status: { type: String, enum: ['completed', 'missed'], default: 'completed' },
  completedAt: { type: Date, default: Date.now },
}, { timestamps: true });

// Compound index to prevent duplicate check-ins for same routine on same day
RoutineCheckInSchema.index({ routine: 1, date: 1 }, { unique: true });

export default mongoose.model<IRoutineCheckIn>('RoutineCheckIn', RoutineCheckInSchema);
