import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Search } from 'lucide-react';

interface SearchBarProps {
    searchQuery: string;
    isSearching: boolean;
    onSearchQueryChange: (value: string) => void;
    onSearch: () => void;
}

export function SearchBar({ searchQuery, isSearching, onSearchQueryChange, onSearch }: SearchBarProps) {
    return (
        <Card>
            <CardContent>
                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2" />
                        <Input
                            placeholder="Search blocks, transactions, addresses..."
                            value={searchQuery}
                            onChange={(event) => onSearchQueryChange(event.target.value)}
                            onKeyDown={(event) => event.key === 'Enter' && onSearch()}
                            className="pl-10"
                            disabled={isSearching}
                        />
                    </div>
                    <Button onClick={onSearch} disabled={isSearching} className="sm:w-auto">
                        {isSearching ? (
                            <>
                                <Spinner data-icon="inline-start" />
                                Searching...
                            </>
                        ) : (
                            <>
                                <Search className="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2" />
                                Search
                            </>
                        )}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
