import { CalendarIcon } from 'lucide-react';

interface LastUpdatedCellProps {
    date: string;
}

export const LastUpdatedCell = ({ date }: LastUpdatedCellProps) => {
    const formattedDate = new Date(date);
    const displayDate = !isNaN(formattedDate.getTime())
        ? formattedDate.toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          })
        : date;

    return (
        <div className="flex items-center gap-1.5">
            <CalendarIcon className="dark:text-muted-foreground h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
            <time className="dark:text-muted-foreground text-sm font-medium text-gray-600" dateTime={date}>
                {displayDate}
            </time>
        </div>
    );
};
