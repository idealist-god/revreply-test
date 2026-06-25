import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';

export function useDraft(draftId: number, threadId: number) {
    const queryClient = useQueryClient();

    const invalidate = () => {
        queryClient.invalidateQueries({ queryKey: ['thread', threadId] });
        queryClient.invalidateQueries({ queryKey: ['threads'] });
    };

    const update = useMutation({
        mutationFn: (body: string) => api.put(`/drafts/${draftId}`, { body }),
        onSuccess: invalidate,
    });

    const approve = useMutation({
        mutationFn: () => api.post(`/drafts/${draftId}/approve`),
        onSuccess: invalidate,
    });

    const reject = useMutation({
        mutationFn: () => api.post(`/drafts/${draftId}/reject`),
        onSuccess: invalidate,
    });

    const regenerate = useMutation({
        mutationFn: () => api.post(`/drafts/${draftId}/regenerate`),
        onSuccess: invalidate,
    });

    return { update, approve, reject, regenerate };
}
