import mongoose from 'mongoose';
import { MongoMemoryServer } from 'mongodb-memory-server';

const connectDB = async () => {
  const primaryUri =
    process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/organizapp';

  try {
    const conn = await mongoose.connect(primaryUri, {
      serverSelectionTimeoutMS: 3000,
    });
    console.log(`MongoDB Connected: ${conn.connection.host}`);
    return;
  } catch (error) {
    const canFallback =
      process.env.DISABLE_MEMORY_DB !== 'true' &&
      process.env.NODE_ENV !== 'production';

    if (!canFallback) {
      if (error instanceof Error) {
        console.error(`MongoDB connection failed: ${error.message}`);
      } else {
        console.error('MongoDB connection failed: Unknown error');
      }
      throw error;
    }

    const memoryServer = await MongoMemoryServer.create();
    const memoryUri = memoryServer.getUri();
    const conn = await mongoose.connect(memoryUri);
    console.log(`MongoDB Connected (Memory): ${conn.connection.host}`);
  }
};

export default connectDB;
