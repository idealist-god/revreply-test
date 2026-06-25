import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import Layout from './components/Layout';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import ConnectPage from './pages/ConnectPage';
import DashboardPage from './pages/DashboardPage';

const queryClient = new QueryClient();

function ProtectedRoute({ children }: { children: React.ReactNode }) {
    if (!localStorage.getItem('token')) {
        return <Navigate to="/login" replace />;
    }
    return <>{children}</>;
}

ReactDOM.createRoot(document.getElementById('app')!).render(
    <React.StrictMode>
        <QueryClientProvider client={queryClient}>
            <BrowserRouter>
                <Routes>
                    <Route path="/login"    element={<LoginPage />} />
                    <Route path="/register" element={<RegisterPage />} />
                    <Route element={<Layout />}>
                        <Route path="/connect"   element={<ProtectedRoute><ConnectPage /></ProtectedRoute>} />
                        <Route path="/dashboard" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
                        <Route path="*"          element={<Navigate to="/dashboard" replace />} />
                    </Route>
                </Routes>
            </BrowserRouter>
        </QueryClientProvider>
    </React.StrictMode>
);
