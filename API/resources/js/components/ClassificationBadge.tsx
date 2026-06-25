const styles: Record<string, string> = {
    interested:      'bg-green-100 text-green-800',
    not_interested:  'bg-red-100 text-red-800',
    meeting_request: 'bg-blue-100 text-blue-800',
    unclear:         'bg-yellow-100 text-yellow-800',
    pending:         'bg-gray-100 text-gray-500',
};

const labels: Record<string, string> = {
    interested:      'Interested',
    not_interested:  'Not Interested',
    meeting_request: 'Meeting Request',
    unclear:         'Unclear',
    pending:         'Pending',
};

export default function ClassificationBadge({ classification }: { classification: string }) {
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${styles[classification] ?? styles.pending}`}>
            {labels[classification] ?? classification}
        </span>
    );
}
