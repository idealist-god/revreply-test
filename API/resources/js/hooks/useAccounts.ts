import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';

export interface GmailAccount {
    id: number;
    gmail_address: string;
    watch_expires_at: string | null;
    created_at: string;
}

export function useAccounts() {
    return useQuery({
        queryKey: ['accounts'],
        queryFn: async () => {
            const { data } = await api.get('/accounts');
            return data as GmailAccount[];
        },
    });
}

export function useDisconnectAccount() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (id: number) => api.delete(`/accounts/${id}`),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['accounts'] });
            queryClient.invalidateQueries({ queryKey: ['threads'] });
        },
    });
}
