import type {
  AddressInfo,
  BlockInfo,
  BlockchainOverview,
  HealthStatus,
  PeerInfo,
  SearchResults,
  StreamInfo,
} from '@/types';
import { router, usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import blockchain from '@/routes/admin/blockchain';

// Re-export types for convenience
export type {
  BlockchainOverview,
  BlockInfo,
  StreamInfo,
  AddressInfo,
  PeerInfo,
  SearchResults,
} from '@/types';

export type {
  CircuitBreakerState,
  QueueMetrics,
  DocumentMetrics,
  HealthStatus,
} from '@/types';

interface UseBlockchainExplorerOptions {
  overview: BlockchainOverview | null;
  latestBlocks: BlockInfo[];
  streams: StreamInfo[];
  addresses: AddressInfo[];
  peers: PeerInfo[];
  health: HealthStatus | null;
}

export function useBlockchainExplorer({
  overview,
  latestBlocks,
  streams,
  addresses,
  peers,
  health,
}: UseBlockchainExplorerOptions) {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedTab, setSelectedTab] = useState('overview');
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [autoRefresh, setAutoRefresh] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [searchResults, setSearchResults] = useState<SearchResults | null>(null);
  const [expandedBlocks, setExpandedBlocks] = useState<Set<string>>(new Set());
  const [expandedPeers, setExpandedPeers] = useState<Set<number>>(new Set());
  const [isResetDialogOpen, setIsResetDialogOpen] = useState(false);

  const isHealthy = health?.status === 'healthy';
  const isCircuitOpen = health?.circuit_breaker?.is_open ?? false;

  // Auto-refresh functionality using Inertia's usePoll
  const { stop, start } = usePoll(
    30000,
    {
      only: ['overview', 'latestBlocks', 'streams', 'addresses', 'peers', 'health'],
      onFinish: () => {
        toast.success('Data refreshed', {
          description: 'Blockchain data has been updated',
          duration: 2000,
        });
      },
    },
    {
      autoStart: false,
      keepAlive: false,
    },
  );

  // Start/stop polling based on autoRefresh toggle
  useEffect(() => {
    if (autoRefresh) {
      start();
    } else {
      stop();
    }
    return () => stop();
  }, [autoRefresh, start, stop]);

  const handleSearch = useCallback(async () => {
    if (!searchQuery.trim()) {
      toast.error('Please enter a search query');
      return;
    }

    setIsSearching(true);
    try {
      const response = await fetch(`${blockchain.explorer.search.url()}?query=${encodeURIComponent(searchQuery)}`);
      const data = await response.json();

      if (data.success) {
        setSearchResults(data.results);
        setSelectedTab('search');
        toast.success('Search completed');
      } else {
        toast.error('Search failed', {
          description: data.error || 'Unable to complete the search. Please try again.',
        });
      }
    } catch {
      toast.error('Search failed', {
        description: 'Unable to complete the search. Please try again.',
      });
    } finally {
      setIsSearching(false);
    }
  }, [searchQuery]);

  const handleResetCircuitBreaker = useCallback(() => {
    router.post(
      blockchain.explorer.reset.url(),
      {},
      {
        onSuccess: () => {
          toast.success('Circuit breaker reset', {
            description: 'Blockchain requests will now resume normally',
          });
          router.reload({ only: ['health'] });
          setIsResetDialogOpen(false);
        },
        onError: () => {
          toast.error('Failed to reset circuit breaker');
          setIsResetDialogOpen(false);
        },
      },
    );
  }, []);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    router.reload({
      onFinish: () => {
        setIsRefreshing(false);
        toast.success('Data refreshed');
      },
    });
  }, []);

  const toggleBlockExpansion = useCallback((hash: string) => {
    setExpandedBlocks((prev) => {
      const next = new Set(prev);
      if (next.has(hash)) {
        next.delete(hash);
      } else {
        next.add(hash);
      }
      return next;
    });
  }, []);

  const togglePeerExpansion = useCallback((id: number) => {
    setExpandedPeers((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  return {
    // Data
    overview,
    latestBlocks,
    streams,
    addresses,
    peers,
    health,
    // State
    searchQuery,
    setSearchQuery,
    selectedTab,
    setSelectedTab,
    isRefreshing,
    autoRefresh,
    setAutoRefresh,
    isSearching,
    searchResults,
    expandedBlocks,
    expandedPeers,
    isResetDialogOpen,
    setIsResetDialogOpen,
    // Computed
    isHealthy,
    isCircuitOpen,
    // Actions
    handleSearch,
    handleResetCircuitBreaker,
    handleRefresh,
    toggleBlockExpansion,
    togglePeerExpansion,
  };
}
