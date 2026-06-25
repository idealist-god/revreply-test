import { useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { useThreads } from '../hooks/useThreads';
import { useThread } from '../hooks/useThread';
import { useAccounts, GmailAccount } from '../hooks/useAccounts';
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

function AccountTabs({
    accounts,
    selected,
    onSelect,
}: {
    accounts: GmailAccount[];
    selected: number | null;
    onSelect: (id: number | null) => void;
}) {
    const tabs = [
        { id: null as number | null, label: 'All inboxes', short: 'All' },
        ...accounts.map((a) => ({ id: a.id, label: a.gmail_address, short: a.gmail_address })),
    ];

    return (
        <div className="flex items-center gap-1 border-b border-gray-200 mb-6 -mx-4 px-4 overflow-x-auto">
            {tabs.map((tab) => {
                const active = tab.id === selected;
                const initial = tab.id === null ? '★' : tab.label[0].toUpperCase();
                return (
                    <button
                        key={String(tab.id)}
                        onClick={() => onSelect(tab.id)}
                        className={`flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                            active
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300'
                        }`}
                    >
                        <span
                            className={`w-5 h-5 rounded-full text-xs flex items-center justify-center font-semibold ${
                                active ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'
                            }`}
                        >
                            {initial}
                        </span>
                        <span className="hidden sm:inline truncate max-w-[180px]">{tab.label}</span>
                        <span className="sm:hidden">{tab.id === null ? 'All' : tab.label.split('@')[0]}</span>
                    </button>
                );
            })}
            <Link
                to="/connect"
                className="flex items-center gap-1.5 px-3 py-3 text-sm text-gray-400 hover:text-blue-600 whitespace-nowrap border-b-2 border-transparent ml-auto"
            >
                <span className="text-base leading-none">+</span>
                <span className="hidden sm:inline">Add account</span>
            </Link>
        </div>
    );
}

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
    const [accountId, setAccountId]           = useState<number | null>(null);

    const { data: accounts = [], isLoading: accountsLoading } = useAccounts();
    const { data, isLoading, error } = useThreads({
        ...(classification ? { classification } : {}),
        ...(accountId      ? { account_id: accountId } : {}),
    });

    if (threadIdParam) {
        return <ThreadDetail threadId={parseInt(threadIdParam, 10)} />;
    }

    const threads = data?.data ?? [];

    const noAccountsYet = !accountsLoading && accounts.length === 0;

    return (
        <div>
            {connected && (
                <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
                    Gmail account connected successfully.
                </div>
            )}

            {/* Account tabs — always visible once loaded */}
            {!accountsLoading && (
                <AccountTabs
                    accounts={accounts}
                    selected={accountId}
                    onSelect={(id) => { setAccountId(id); setClassification(''); }}
                />
            )}

            {/* No accounts connected yet */}
            {noAccountsYet && (
                <div className="text-center py-20 text-gray-400">
                    <p className="text-lg font-medium text-gray-600 mb-2">No Gmail accounts connected</p>
                    <p className="text-sm mb-6">Connect a Gmail account to start classifying and replying to emails.</p>
                    <Link
                        to="/connect"
                        className="inline-flex items-center gap-2 bg-blue-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Connect Gmail
                    </Link>
                </div>
            )}

            {/* Classification filter */}
            {!noAccountsYet && (
                <div className="flex gap-2 mb-5 flex-wrap">
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
            )}

            {isLoading && <p className="text-sm text-gray-400">Loading threads…</p>}
            {error    && <p className="text-sm text-red-600">Failed to load threads.</p>}

            {!isLoading && !noAccountsYet && threads.length === 0 && (
                <div className="text-center py-16 text-gray-400">
                    <p className="text-lg">No threads yet.</p>
                    <p className="text-sm mt-1">
                        Run <code className="bg-gray-100 px-1 rounded">php artisan gmail:sync</code> to pull emails.
                    </p>
                </div>
            )}

            <div className="flex flex-col gap-3">
                {threads.map((thread: any) => (
                    <ThreadCard
                        key={thread.id}
                        thread={thread}
                        accountEmail={
                            accountId === null && accounts.length > 1
                                ? accounts.find((a) => a.id === thread.gmail_account_id)?.gmail_address
                                : undefined
                        }
                    />
                ))}
            </div>
        </div>
    );
}
