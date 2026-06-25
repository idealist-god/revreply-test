import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import api from '../lib/api';

export default function RegisterPage() {
    const navigate = useNavigate();
    const [form, setForm]       = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const [error, setError]     = useState('');
    const [loading, setLoading] = useState(false);

    const handle = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const { data } = await api.post('/auth/register', form);
            localStorage.setItem('token', data.token);
            navigate('/connect');
        } catch (err: any) {
            const errors = err.response?.data?.errors;
            setError(errors ? Object.values(errors).flat().join(' ') : 'Registration failed.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-sm mx-auto mt-20">
            <h1 className="text-2xl font-bold text-gray-900 mb-6">Create account</h1>
            <form onSubmit={handle} className="flex flex-col gap-4">
                {(['name', 'email', 'password', 'password_confirmation'] as const).map((field) => (
                    <input
                        key={field}
                        type={field.includes('password') ? 'password' : field === 'email' ? 'email' : 'text'}
                        placeholder={field === 'password_confirmation' ? 'Confirm password' : field.charAt(0).toUpperCase() + field.slice(1)}
                        value={form[field]}
                        onChange={(e) => setForm({ ...form, [field]: e.target.value })}
                        className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    />
                ))}
                {error && <p className="text-sm text-red-600">{error}</p>}
                <button
                    type="submit"
                    disabled={loading}
                    className="bg-blue-600 text-white font-medium py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
                >
                    {loading ? 'Creating account…' : 'Create account'}
                </button>
                <p className="text-sm text-center text-gray-500">
                    Already have an account?{' '}
                    <Link to="/login" className="text-blue-600 hover:underline">Sign in</Link>
                </p>
            </form>
        </div>
    );
}
