import { Outlet, Link, useNavigate } from 'react-router-dom';
import api from '../lib/api';

export default function Layout() {
    const navigate = useNavigate();

    const logout = async () => {
        try { await api.post('/auth/logout'); } catch { /* ignore */ }
        localStorage.removeItem('token');
        navigate('/login');
    };

    return (
        <div className="bg-gray-50 min-h-screen">
            <nav className="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
                <Link to="/dashboard" className="font-semibold text-gray-900">RevReply</Link>
                <div className="flex items-center gap-4">
                    <Link to="/connect" className="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                        Connect Gmail
                    </Link>
                    <button
                        onClick={logout}
                        className="text-sm text-gray-500 hover:text-red-600 transition-colors"
                    >
                        Logout
                    </button>
                </div>
            </nav>
            <main className="max-w-5xl mx-auto px-4 py-6">
                <Outlet />
            </main>
        </div>
    );
}
