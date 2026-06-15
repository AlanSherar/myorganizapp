import React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import Tasks from './pages/Tasks';
import Finance from './pages/Finance';
import Routines from './pages/Routines';
import { TaskProvider } from './context/TaskContext';
import { FinanceProvider } from './context/FinanceContext';
import { RoutineProvider } from './context/RoutineContext';

const App: React.FC = () => {
  return (
    <BrowserRouter>
      <TaskProvider>
        <FinanceProvider>
          <RoutineProvider>
            <Layout>
              <Routes>
                <Route path="/" element={<Dashboard />} />
                <Route path="/tasks" element={<Tasks />} />
                <Route path="/finance" element={<Finance />} />
                <Route path="/routines" element={<Routines />} />
              </Routes>
            </Layout>
          </RoutineProvider>
        </FinanceProvider>
      </TaskProvider>
    </BrowserRouter>
  );
};

export default App;
