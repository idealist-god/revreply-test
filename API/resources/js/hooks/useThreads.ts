import { useQuery } from '@tanstack/react-query';
import api from '../lib/api';

export interface Draft {
    id: number;
    body: string;
    gmail_draft_id: string | null;
    status: 'pending' | 'approved' | 'sent' | 'rejected';
    sent_at: string | null;
}

export interface Thread {
    id: number;
    gmail_account_id: number;
    gmail_thread_id: string;
    subject: string | null;
    snippet: string | null;
    from_address: string | null;
    last_message_at: string | null;
    classification: 'pending' | 'interested' | 'not_interested' | 'meeting_request' | 'unclear';
    status: 'new' | 'draft_created' | 'sent' | 'dismissed';
    messages_count: number;
    latest_draft: Draft | null;
}

export interface ThreadFilters {
    classification?: string;
    status?: string;
    account_id?: number;
}

export function useThreads(filters: ThreadFilters = {}) {
    return useQuery({
        queryKey: ['threads', filters],
        queryFn: async () => {
            const { data } = await api.get('/threads', { params: filters });
            return data;
        },
    });
}
