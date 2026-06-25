import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';

export default function ConnectPage() {
    const [loading, setLoading] = useState(false);
    const [error, setError]     = useState('');

    const connect = async () => {
        setError('');
        setLoading(true);
        try {
            const { data } = await api.get('/auth/google/redirect');
            window.location.href = data.url;
        } catch {
            setError('Could not start Google authorization. Please try again.');
            setLoading(false);
        }
    };

    return (
        <div className="max-w-sm mx-auto mt-20 text-center">
            <h1 className="text-2xl font-bold text-gray-900 mb-2">Connect Gmail</h1>
            <p className="text-sm text-gray-500 mb-8">
                Grant access to your inbox so RevReply can classify emails and generate draft replies.
            </p>
            {error && <p className="text-sm text-red-600 mb-4">{error}</p>}
            <button
                onClick={connect}
                disabled={loading}
                className="bg-blue-600 text-white font-medium py-2 px-6 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
            >
                {loading ? 'Redirecting…' : 'Connect Gmail Account'}
            </button>
            <p className="mt-4">
                <Link to="/dashboard" className="text-sm text-gray-400 hover:underline">Skip for now</Link>
            </p>
        </div>
    );
}
