import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';
import { useAccounts, useDisconnectAccount } from '../hooks/useAccounts';

export default function ConnectPage() {
    const [connecting, setConnecting] = useState(false);
    const [error, setError]           = useState('');

    const { data: accounts = [], isLoading } = useAccounts();
    const disconnect = useDisconnectAccount();

    const connect = async () => {
        setError('');
        setConnecting(true);
        try {
            const { data } = await api.get('/auth/google/redirect');
            window.location.href = data.url;
        } catch {
            setError('Could not start Google authorization. Please try again.');
            setConnecting(false);
        }
    };

    const handleDisconnect = (id: number, email: string) => {
        if (!window.confirm(`Disconnect ${email}? This will remove all synced threads for this account.`)) return;
        disconnect.mutate(id);
    };

    return (
        <div className="max-w-lg mx-auto mt-12">
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Gmail Accounts</h1>
                <Link to="/dashboard" className="text-sm text-gray-400 hover:underline">← Dashboard</Link>
            </div>

            {/* Connected accounts list */}
            {isLoading ? (
                <p className="text-sm text-gray-400 mb-6">Loading accounts…</p>
            ) : accounts.length === 0 ? (
                <p className="text-sm text-gray-500 mb-6">No Gmail accounts connected yet.</p>
            ) : (
                <div className="flex flex-col gap-3 mb-8">
                    {accounts.map((acc) => (
                        <div
                            key={acc.id}
                            className="flex items-center justify-between bg-white border border-gray-200 rounded-lg px-4 py-3"
                        >
                            <div className="flex items-center gap-3">
                                <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-sm font-semibold">
                                    {acc.gmail_address[0].toUpperCase()}
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{acc.gmail_address}</p>
                                    <p className="text-xs text-gray-400">
                                        {acc.watch_expires_at
                                            ? `Watch expires ${new Date(acc.watch_expires_at).toLocaleDateString()}`
                                            : 'No push subscription'}
                                    </p>
                                </div>
                            </div>
                            <button
                                onClick={() => handleDisconnect(acc.id, acc.gmail_address)}
                                disabled={disconnect.isPending}
                                className="text-xs text-red-500 hover:text-red-700 disabled:opacity-50 transition-colors"
                            >
                                Disconnect
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Connect button */}
            {error && <p className="text-sm text-red-600 mb-3">{error}</p>}
            <button
                onClick={connect}
                disabled={connecting}
                className="w-full bg-blue-600 text-white font-medium py-2.5 px-6 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
            >
                {connecting ? 'Redirecting to Google…' : accounts.length > 0 ? '+ Connect another Gmail account' : 'Connect Gmail Account'}
            </button>
            <p className="text-xs text-gray-400 text-center mt-3">
                You can connect multiple Gmail addresses to the same RevReply account.
            </p>
        </div>
    );
}
