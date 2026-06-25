import { Link } from 'react-router-dom';
import ClassificationBadge from './ClassificationBadge';
import { Thread } from '../hooks/useThreads';

function timeAgo(dateStr: string | null): string {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

export default function ThreadCard({ thread, accountEmail }: { thread: Thread; accountEmail?: string }) {
    return (
        <Link to={`/dashboard?threadId=${thread.id}`}>
            <div className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <p className="text-sm font-medium text-gray-900 truncate">
                                {thread.from_address ?? 'Unknown sender'}
                            </p>
                            {accountEmail && (
                                <span className="shrink-0 text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">
                                    {accountEmail}
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-gray-700 truncate mt-0.5">
                            {thread.subject ?? '(no subject)'}
                        </p>
                        <p className="text-xs text-gray-400 truncate mt-1">
                            {thread.snippet}
                        </p>
                    </div>
                    <div className="flex flex-col items-end gap-1 shrink-0">
                        <span className="text-xs text-gray-400">{timeAgo(thread.last_message_at)}</span>
                        <ClassificationBadge classification={thread.classification} />
                        {thread.latest_draft?.status === 'pending' && (
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
                                Draft Ready
                            </span>
                        )}
                    </div>
                </div>
            </div>
        </Link>
    );
}
