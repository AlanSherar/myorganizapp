# OrganizAPP

All-in-one life management application.

## Tech Stack
- Frontend: React.js (Vite), Tailwind CSS, Lucide React
- Backend: Node.js, Express.js
- Database: MongoDB

## Setup

1. Install dependencies:
   ```bash
   npm install
   ```

2. Ensure MongoDB is running locally on `mongodb://localhost:27017/organizapp` or set `MONGO_URI` in `.env`.

3. Run the development server (Frontend + Backend):
   ```bash
   npm run dev
   ```

## Features
- **Task Management**: One-time and Recurring tasks.
- **Finance Manager**: Track income/expenses and import statements (BBVA, Nacion, MercadoPago).
- **Routine Tracker**: Discipline score and daily check-ins.
