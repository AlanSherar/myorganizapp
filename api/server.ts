/**
 * local server entry file, for local development
 */
import app from './app.js';
import connectDB from './config/db.js';

const PORT = process.env.PORT || 3001;

const start = async () => {
  await connectDB();

  const server = app.listen(PORT, () => {
    console.log(`Server ready on port ${PORT}`);
  });

  process.on('SIGTERM', () => {
    console.log('SIGTERM signal received');
    server.close(() => {
      console.log('Server closed');
      process.exit(0);
    });
  });

  process.on('SIGINT', () => {
    console.log('SIGINT signal received');
    server.close(() => {
      console.log('Server closed');
      process.exit(0);
    });
  });
};

start().catch((error) => {
  if (error instanceof Error) {
    console.error(`Server failed to start: ${error.message}`);
  } else {
    console.error('Server failed to start');
  }
  process.exit(1);
});

export default app;
