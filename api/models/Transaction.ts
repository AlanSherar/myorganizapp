import mongoose, { Document, Schema } from 'mongoose';

export interface ITransaction extends Document {
  user: mongoose.Types.ObjectId;
  amount: number;
  date: Date;
  category: string;
  sourceBank: string;
  type: 'income' | 'expense';
  description?: string;
}

const TransactionSchema: Schema = new Schema({
  user: { type: Schema.Types.ObjectId, ref: 'User', required: true },
  amount: { type: Number, required: true },
  date: { type: Date, required: true },
  category: { type: String, required: true },
  sourceBank: { type: String, required: true },
  type: { type: String, enum: ['income', 'expense'], required: true },
  description: { type: String },
}, { timestamps: true });

export default mongoose.model<ITransaction>('Transaction', TransactionSchema);
