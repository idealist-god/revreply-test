import { useQuery } from '@tanstack/react-query';
import api from '../lib/api';

export function useThread(id: number) {
    return useQuery({
        queryKey: ['thread', id],
        queryFn: async () => {
            const { data } = await api.get(`/threads/${id}`);
            return data;
        },
        enabled: !!id,
    });
}
