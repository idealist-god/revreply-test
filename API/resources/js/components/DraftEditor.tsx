import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDraft } from '../hooks/useDraft';
import { Draft } from '../hooks/useThreads';

export default function DraftEditor({ draft, threadId }: { draft: Draft; threadId: number }) {
    const navigate = useNavigate();
    const [body, setBody] = useState(draft.body);
    const { update, approve, reject, regenerate } = useDraft(draft.id, threadId);

    const handleApprove = async () => {
        if (body !== draft.body) await update.mutateAsync(body);
        await approve.mutateAsync();
        navigate('/dashboard');
    };

    const handleReject = async () => {
        await reject.mutateAsync();
        navigate('/dashboard');
    };

    const handleRegenerate = async () => {
        await regenerate.mutateAsync();
    };

    const busy = update.isPending || approve.isPending || reject.isPending || regenerate.isPending;

    return (
        <div className="flex flex-col gap-4">
            <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Draft Reply</h2>
            <textarea
                className="w-full h-64 p-3 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"
                value={body}
                onChange={(e) => setBody(e.target.value)}
                disabled={busy}
            />
            <div className="flex gap-2">
                <button
                    onClick={handleApprove}
                    disabled={busy}
                    className="flex-1 bg-green-600 text-white text-sm font-medium py-2 px-4 rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors"
                >
                    {approve.isPending ? 'Sending…' : 'Approve & Send'}
                </button>
                <button
                    onClick={handleRegenerate}
                    disabled={busy}
                    className="flex-1 bg-blue-600 text-white text-sm font-medium py-2 px-4 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
                >
                    {regenerate.isPending ? 'Queuing…' : 'Regenerate'}
                </button>
                <button
                    onClick={handleReject}
                    disabled={busy}
                    className="flex-1 bg-red-600 text-white text-sm font-medium py-2 px-4 rounded-lg hover:bg-red-700 disabled:opacity-50 transition-colors"
                >
                    {reject.isPending ? 'Rejecting…' : 'Reject'}
                </button>
            </div>
        </div>
    );
}
