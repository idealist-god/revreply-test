import { useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { useThreads } from '../hooks/useThreads';
import { useThread } from '../hooks/useThread';
import ThreadCard from '../components/ThreadCard';
import ClassificationBadge from '../components/ClassificationBadge';
import DraftEditor from '../components/DraftEditor';
import api from '../lib/api';

const FILTERS = [
    { label: 'All',             value: '' },
    { label: 'Interested',      value: 'interested' },
    { label: 'Meeting Request', value: 'meeting_request' },
    { label: 'Unclear',         value: 'unclear' },
    { label: 'Not Interested',  value: 'not_interested' },
];

function ThreadDetail({ threadId }: { threadId: number }) {
    const navigate = useNavigate();
    const { data: thread, isLoading } = useThread(threadId);

    const dismiss = async () => {
        await api.patch(`/threads/${threadId}/dismiss`);
        navigate('/dashboard');
    };

    if (isLoading) return <p className="text-sm text-gray-400">Loading…</p>;
    if (!thread)   return <p className="text-sm text-red-600">Thread not found.</p>;

    const latestMessage = thread.messages?.[thread.messages.length - 1];
    const draft         = thread.latest_draft;

    return (
        <div>
            <div className="flex items-center gap-3 mb-6">
                <button onClick={() => navigate('/dashboard')} className="text-sm text-gray-400 hover:text-gray-600">← Back</button>
                <h1 className="text-lg font-semibold text-gray-900 flex-1 truncate">{thread.subject ?? '(no subject)'}</h1>
                <ClassificationBadge classification={thread.classification} />
                <button onClick={dismiss} className="text-sm text-gray-400 hover:text-red-500 transition-colors">Dismiss</button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="bg-white border border-gray-200 rounded-lg p-4">
                    <div className="text-xs text-gray-400 mb-3">
                        <span className="font-medium text-gray-700">From:</span> {latestMessage?.from_address}
                    </div>
                    <div className="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">
                        {latestMessage?.body_text ?? latestMessage?.body_html ?? 'No body content.'}
                    </div>
                </div>

                <div className="bg-white border border-gray-200 rounded-lg p-4">
                    {draft?.status === 'pending' ? (
                        <DraftEditor draft={draft} threadId={threadId} />
                    ) : draft?.status === 'sent' ? (
                        <div className="text-sm text-green-700 bg-green-50 rounded p-3">Reply sent.</div>
                    ) : (
                        <div className="text-sm text-gray-400 text-center py-8">
                            {thread.classification === 'pending'
                                ? 'Classification in progress…'
                                : 'Draft generation in progress…'}
                            <p className="mt-2 text-xs">Refresh to check.</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function DashboardPage() {
    const [searchParams]                       = useSearchParams();
    const connected                            = searchParams.get('connected');
    const threadIdParam                        = searchParams.get('threadId');
    const [classification, setClassification] = useState('');
    const { data, isLoading, error }           = useThreads(classification ? { classification } : {});

    if (threadIdParam) {
        return <ThreadDetail threadId={parseInt(threadIdParam, 10)} />;
    }

    const threads = data?.data ?? [];

    return (
        <div>
            {connected && (
                <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
                    Gmail account connected successfully.
                </div>
            )}

            <div className="flex items-center justify-between mb-4">
                <h1 className="text-xl font-bold text-gray-900">Inbox</h1>
                <Link to="/connect" className="text-sm text-blue-600 hover:underline">+ Connect Gmail</Link>
            </div>

            <div className="flex gap-2 mb-6 flex-wrap">
                {FILTERS.map((f) => (
                    <button
                        key={f.value}
                        onClick={() => setClassification(f.value)}
                        className={`px-3 py-1 rounded-full text-sm font-medium transition-colors ${
                            classification === f.value
                                ? 'bg-blue-600 text-white'
                                : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'
                        }`}
                    >
                        {f.label}
                    </button>
                ))}
            </div>

            {isLoading && <p className="text-sm text-gray-400">Loading threads…</p>}
            {error    && <p className="text-sm text-red-600">Failed to load threads.</p>}

            {!isLoading && threads.length === 0 && (
                <div className="text-center py-16 text-gray-400">
                    <p className="text-lg">No threads yet.</p>
                    <p className="text-sm mt-1">Connect Gmail and run <code className="bg-gray-100 px-1 rounded">php artisan gmail:sync</code> to pull emails.</p>
                </div>
            )}

            <div className="flex flex-col gap-3">
                {threads.map((thread: any) => (
                    <ThreadCard key={thread.id} thread={thread} />
                ))}
            </div>
        </div>
    );
}
