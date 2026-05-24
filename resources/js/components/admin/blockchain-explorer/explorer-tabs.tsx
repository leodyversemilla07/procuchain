import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TabsContent } from '@/components/ui/tabs';
import { formatBlockchainDate, formatBytes, formatPingTime, getSyncStatus, truncateHash } from '@/lib/blockchain-explorer';
import { cn } from '@/lib/utils';
import type { AddressInfo, BlockInfo, PeerInfo, SearchResults, StreamInfo } from '@/types';
import { formatDistanceToNow } from 'date-fns';
import { Blocks, ChevronRight, Database, Users, Wallet } from 'lucide-react';
import React from 'react';

interface ExplorerTabsProps {
  overview: {
    chain: string;
    protocol: string;
    blocks: number;
    difficulty: number;
    connections: number;
    version: string;
    nodeaddress: string;
  } | null;
  latestBlocks: BlockInfo[];
  streams: StreamInfo[];
  addresses: AddressInfo[];
  peers: PeerInfo[];
  searchResults: SearchResults | null;
  searchQuery: string;
  expandedBlocks: Set<string>;
  expandedPeers: Set<number>;
  onToggleBlockExpansion: (hash: string) => void;
  onTogglePeerExpansion: (id: number) => void;
}

export function ExplorerTabs({
  overview,
  latestBlocks,
  streams,
  addresses,
  peers,
  searchResults,
  searchQuery,
  expandedBlocks,
  expandedPeers,
  onToggleBlockExpansion,
  onTogglePeerExpansion,
}: ExplorerTabsProps) {
  return (
    <>
      {/* Overview Tab */}
      <TabsContent value="overview">
        {overview && (
          <div className="grid gap-6 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>Chain Summary</CardTitle>
                <CardDescription>Current blockchain state and parameters</CardDescription>
              </CardHeader>
              <CardContent>
                <dl className="space-y-3 text-sm">
                  <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                    <dt className="text-muted-foreground font-medium">Chain Name</dt>
                    <dd className="text-right sm:text-left">{overview.chain}</dd>
                  </div>
                  <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                    <dt className="text-muted-foreground font-medium">Protocol Version</dt>
                    <dd className="text-right sm:text-left">{overview.protocol}</dd>
                  </div>
                  <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                    <dt className="text-muted-foreground font-medium">Blocks</dt>
                    <dd className="text-right font-mono sm:text-left">{overview.blocks.toLocaleString()}</dd>
                  </div>
                  <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                    <dt className="text-muted-foreground font-medium">Difficulty</dt>
                    <dd className="text-right font-mono sm:text-left">{overview.difficulty.toFixed(8)}</dd>
                  </div>
                  <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                    <dt className="text-muted-foreground font-medium">Connections</dt>
                    <dd className="text-right sm:text-left">{overview.connections}</dd>
                  </div>
                  <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                    <dt className="text-muted-foreground font-medium">Streams</dt>
                    <dd className="text-right sm:text-left">{streams.length}</dd>
                  </div>
                  <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                    <dt className="text-muted-foreground font-medium">Addresses</dt>
                    <dd className="text-right sm:text-left">{addresses.length}</dd>
                  </div>
                </dl>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Node Information</CardTitle>
                <CardDescription>Local node details and configuration</CardDescription>
              </CardHeader>
              <CardContent>
                <dl className="space-y-3 text-sm">
                  <div className="flex justify-between gap-4 sm:grid sm:grid-cols-[140px_1fr]">
                    <dt className="text-muted-foreground font-medium">Version</dt>
                    <dd className="text-right sm:text-left">{overview.version}</dd>
                  </div>
                  <div className="flex flex-col gap-2 sm:grid sm:grid-cols-[140px_1fr] sm:gap-4">
                    <dt className="text-muted-foreground font-medium">Node Address</dt>
                    <dd className="font-mono text-xs break-all">{overview.nodeaddress}</dd>
                  </div>
                </dl>
              </CardContent>
            </Card>

            <Card className="md:col-span-2">
              <CardHeader>
                <CardTitle>Recent Blocks</CardTitle>
                <CardDescription>Latest blocks added to the blockchain</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-3 md:hidden">
                  {latestBlocks.slice(0, 10).map((block) => (
                    <Card key={block.hash} className="p-3">
                      <div className="space-y-2">
                        <div className="flex items-center justify-between gap-2">
                          <Badge variant="outline" className="text-xs">#{block.height}</Badge>
                          <span className="text-muted-foreground text-xs">
                            {formatDistanceToNow(new Date(block.time * 1000), { addSuffix: true })}
                          </span>
                        </div>
                        <div className="space-y-1">
                          <div className="text-muted-foreground text-xs">Hash</div>
                          <div className="font-mono text-xs break-all">{truncateHash(block.hash, 20)}</div>
                        </div>
                        <div className="grid grid-cols-2 gap-3 text-sm">
                          <div><div className="text-muted-foreground text-xs">Transactions</div><div className="font-medium">{block.tx_count}</div></div>
                          <div><div className="text-muted-foreground text-xs">Size</div><div className="font-medium">{formatBytes(block.size)}</div></div>
                        </div>
                      </div>
                    </Card>
                  ))}
                </div>
                <div className="hidden overflow-x-auto md:block">
                  <Table>
                    <TableHeader><TableRow><TableHead>Height</TableHead><TableHead>Hash</TableHead><TableHead>Miner</TableHead><TableHead className="text-right">Transactions</TableHead><TableHead className="text-right">Size</TableHead><TableHead>Time</TableHead></TableRow></TableHeader>
                    <TableBody>
                      {latestBlocks.slice(0, 10).map((block) => (
                        <TableRow key={block.hash} className="hover:bg-accent">
                          <TableCell className="font-medium">{block.height}</TableCell>
                          <TableCell className="font-mono text-xs">{truncateHash(block.hash, 16)}</TableCell>
                          <TableCell className="font-mono text-xs">{truncateHash(block.miner, 16)}</TableCell>
                          <TableCell className="text-right">{block.tx_count}</TableCell>
                          <TableCell className="text-right">{formatBytes(block.size)}</TableCell>
                          <TableCell className="text-muted-foreground text-xs">{formatBlockchainDate(block.time)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </CardContent>
            </Card>
          </div>
        )}
      </TabsContent>

      {/* Blocks Tab */}
      <TabsContent value="blocks">
        <Card>
          <CardHeader><CardTitle>Recent Blocks</CardTitle><CardDescription>Latest blocks mined on the blockchain</CardDescription></CardHeader>
          <CardContent>
            <div className="space-y-3 md:hidden">
              {latestBlocks.map((block) => (
                <Card key={block.hash} className="p-4">
                  <button onClick={() => onToggleBlockExpansion(block.hash)} className="w-full touch-manipulation">
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex-1 space-y-2 text-left">
                        <div className="flex items-center gap-2">
                          <Badge variant="outline">#{block.height}</Badge>
                          <span className="text-muted-foreground text-xs">{formatDistanceToNow(new Date(block.time * 1000), { addSuffix: true })}</span>
                        </div>
                        <div className="space-y-1"><div className="text-muted-foreground text-xs">Hash</div><div className="font-mono text-xs break-all">{truncateHash(block.hash, 20)}</div></div>
                        <div className="flex gap-4 text-sm">
                          <div><span className="text-muted-foreground">Txs: </span><span className="font-medium">{block.tx_count}</span></div>
                          <div><span className="text-muted-foreground">Size: </span><span className="font-medium">{formatBytes(block.size)}</span></div>
                        </div>
                      </div>
                      <ChevronRight className={cn('text-muted-foreground h-5 w-5 shrink-0 transition-transform', expandedBlocks.has(block.hash) && 'rotate-90')} />
                    </div>
                  </button>
                  {expandedBlocks.has(block.hash) && (
                    <div className="mt-3 space-y-3 border-t pt-3">
                      <div><div className="text-muted-foreground text-xs">Full Hash</div><div className="mt-1 font-mono text-xs break-all">{block.hash}</div></div>
                      <div><div className="text-muted-foreground text-xs">Miner Address</div><div className="mt-1 font-mono text-xs break-all">{block.miner}</div></div>
                      <div><div className="text-muted-foreground text-xs">Time</div><div className="mt-1 text-sm">{formatBlockchainDate(block.time)}</div></div>
                    </div>
                  )}
                </Card>
              ))}
            </div>
            <div className="hidden overflow-x-auto md:block">
              <Table>
                <TableHeader><TableRow><TableHead className="w-12"></TableHead><TableHead>Height</TableHead><TableHead>Hash</TableHead><TableHead>Miner</TableHead><TableHead>Transactions</TableHead><TableHead>Size</TableHead><TableHead>Time</TableHead></TableRow></TableHeader>
                <TableBody>
                  {latestBlocks.map((block) => (
                    <React.Fragment key={block.hash}>
                      <TableRow className="hover:bg-muted/50 cursor-pointer" onClick={() => onToggleBlockExpansion(block.hash)}>
                        <TableCell><ChevronRight className={cn('text-muted-foreground h-4 w-4 transition-transform', expandedBlocks.has(block.hash) && 'rotate-90')} /></TableCell>
                        <TableCell className="font-medium">{block.height}</TableCell>
                        <TableCell className="font-mono text-xs">{truncateHash(block.hash, 16)}</TableCell>
                        <TableCell className="font-mono text-xs">{truncateHash(block.miner, 16)}</TableCell>
                        <TableCell>{block.tx_count}</TableCell>
                        <TableCell>{formatBytes(block.size)}</TableCell>
                        <TableCell className="text-muted-foreground text-xs">
                          <div className="flex flex-col gap-1">
                            <span>{formatBlockchainDate(block.time)}</span>
                            <span className="text-muted-foreground text-xs">({formatDistanceToNow(new Date(block.time * 1000), { addSuffix: true })})</span>
                          </div>
                        </TableCell>
                      </TableRow>
                      {expandedBlocks.has(block.hash) && (
                        <TableRow><TableCell colSpan={7} className="bg-muted/20">
                          <Collapsible open><CollapsibleContent className="px-4 py-3">
                            <div className="space-y-2">
                              <p className="text-sm font-medium">Block Details</p>
                              <div className="grid gap-2 text-sm">
                                <div className="flex gap-2"><span className="text-muted-foreground font-medium">Full Hash:</span><span className="font-mono break-all">{block.hash}</span></div>
                                <div className="flex gap-2"><span className="text-muted-foreground font-medium">Miner Address:</span><span className="font-mono break-all">{block.miner}</span></div>
                                <div className="flex gap-2"><span className="text-muted-foreground font-medium">Block Size:</span><span>{formatBytes(block.size)}</span></div>
                                <div className="flex gap-2"><span className="text-muted-foreground font-medium">Transaction Count:</span><span>{block.tx_count} transactions</span></div>
                              </div>
                            </div>
                          </CollapsibleContent></Collapsible>
                        </TableCell></TableRow>
                      )}
                    </React.Fragment>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      {/* Streams Tab */}
      <TabsContent value="streams">
        <Card>
          <CardHeader><CardTitle>Blockchain Streams</CardTitle><CardDescription>Data streams configured on the blockchain</CardDescription></CardHeader>
          <CardContent>
            {streams.length === 0 ? (
              <Empty><EmptyHeader><EmptyMedia variant="icon"><Database /></EmptyMedia><EmptyTitle>No Streams Found</EmptyTitle><EmptyDescription>There are no blockchain streams configured yet.</EmptyDescription></EmptyHeader></Empty>
            ) : (
              <>
                <div className="space-y-3 md:hidden">
                  {streams.map((stream) => (
                    <Card key={stream.name} className="p-4">
                      <div className="space-y-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                          <h3 className="font-medium break-all">{stream.name}</h3>
                          <div className="flex flex-wrap gap-1">
                            {stream.subscribed && <Badge variant="default" className="text-xs">Subscribed</Badge>}
                            {stream.synchronized && <Badge variant="secondary" className="text-xs">Synced</Badge>}
                          </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3 text-sm">
                          <div><div className="text-muted-foreground text-xs">Items</div><div className="font-medium">{stream.items.toLocaleString()}</div></div>
                          <div><div className="text-muted-foreground text-xs">Keys</div><div className="font-medium">{stream.keys.toLocaleString()}</div></div>
                          <div><div className="text-muted-foreground text-xs">Publishers</div><div className="font-medium">{stream.publishers}</div></div>
                          <div><div className="text-muted-foreground text-xs">Confirmed</div><div className="font-medium">{stream.confirmed.toLocaleString()}</div></div>
                        </div>
                      </div>
                    </Card>
                  ))}
                </div>
                <div className="hidden overflow-x-auto md:block">
                  <Table>
                    <TableHeader><TableRow><TableHead>Stream Name</TableHead><TableHead>Items</TableHead><TableHead>Keys</TableHead><TableHead>Publishers</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                    <TableBody>
                      {streams.map((stream) => (
                        <TableRow key={stream.name}>
                          <TableCell className="font-medium">{stream.name}</TableCell>
                          <TableCell>{stream.items.toLocaleString()}</TableCell>
                          <TableCell>{stream.keys.toLocaleString()}</TableCell>
                          <TableCell>{stream.publishers}</TableCell>
                          <TableCell><div className="flex flex-wrap gap-1">{stream.subscribed && <Badge variant="default">Subscribed</Badge>}{stream.synchronized && <Badge variant="secondary">Synced</Badge>}</div></TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      </TabsContent>

      {/* Addresses Tab */}
      <TabsContent value="addresses">
        <Card>
          <CardHeader><CardTitle>Wallet Addresses</CardTitle><CardDescription>Blockchain addresses managed by this node</CardDescription></CardHeader>
          <CardContent>
            {addresses.length === 0 ? (
              <Empty><EmptyHeader><EmptyMedia variant="icon"><Wallet /></EmptyMedia><EmptyTitle>No Wallet Addresses</EmptyTitle><EmptyDescription>There are no wallet addresses configured for this node.</EmptyDescription></EmptyHeader></Empty>
            ) : (
              <>
                <div className="space-y-3 md:hidden">
                  {addresses.map((address) => (
                    <Card key={address.address} className="p-4">
                      <div className="space-y-2">
                        <div className="flex items-start justify-between gap-2">
                          <div className="text-muted-foreground text-xs">Address</div>
                          {address.ismine && <Badge variant="default" className="text-xs">Mine</Badge>}
                        </div>
                        <div className="font-mono text-sm break-all">{address.address}</div>
                      </div>
                    </Card>
                  ))}
                </div>
                <div className="hidden overflow-x-auto md:block">
                  <Table>
                    <TableHeader><TableRow><TableHead>Address</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                    <TableBody>
                      {addresses.map((address) => (
                        <TableRow key={address.address}>
                          <TableCell className="font-mono text-sm">{address.address}</TableCell>
                          <TableCell>{address.ismine && <Badge variant="default">Mine</Badge>}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      </TabsContent>

      {/* Peers Tab */}
      <TabsContent value="peers">
        <Card>
          <CardHeader><CardTitle>Network Peers</CardTitle><CardDescription>Connected nodes in the network with detailed connection metrics</CardDescription></CardHeader>
          <CardContent>
            {peers.length > 0 ? (
              <>
                <div className="space-y-3 md:hidden">
                  {peers.map((peer) => (
                    <Card key={peer.id} className="p-4">
                      <button onClick={() => onTogglePeerExpansion(peer.id)} className="w-full touch-manipulation">
                        <div className="flex items-start justify-between gap-3">
                          <div className="flex-1 space-y-2 text-left">
                            <div className="font-mono text-sm break-all">{peer.addr}</div>
                            <div className="flex flex-wrap gap-2">
                              <Badge variant={peer.inbound ? 'secondary' : 'default'} className="text-xs">{peer.inbound ? 'Inbound' : 'Outbound'}</Badge>
                              <Badge variant={peer.synced_blocks >= (peer.startingheight || 0) ? 'default' : 'secondary'} className="text-xs">{getSyncStatus(peer.synced_blocks || 0, peer.startingheight || 0)}</Badge>
                            </div>
                            <div className="text-muted-foreground flex gap-4 text-xs">
                              <span>Ping: {formatPingTime(peer.pingtime)}</span>
                              <span>Score: {peer.banscore || 0}</span>
                            </div>
                          </div>
                          <ChevronRight className={cn('text-muted-foreground h-5 w-5 shrink-0 transition-transform', expandedPeers.has(peer.id) && 'rotate-90')} />
                        </div>
                      </button>
                      {expandedPeers.has(peer.id) && (
                        <div className="mt-3 space-y-3 border-t pt-3">
                          <div className="grid grid-cols-2 gap-3 text-sm">
                            <div><div className="text-muted-foreground text-xs">Version</div><div className="mt-1">{peer.subver}</div></div>
                            <div><div className="text-muted-foreground text-xs">Time Offset</div><div className="mt-1">{peer.timeoffset || 0}s</div></div>
                            <div><div className="text-muted-foreground text-xs">Data Sent</div><div className="mt-1">{formatBytes(peer.bytessent || 0)}</div></div>
                            <div><div className="text-muted-foreground text-xs">Data Received</div><div className="mt-1">{formatBytes(peer.bytesrecv || 0)}</div></div>
                            <div><div className="text-muted-foreground text-xs">Connected</div><div className="mt-1">{peer.conntime ? formatDistanceToNow(new Date(peer.conntime * 1000), { addSuffix: true }) : 'Unknown'}</div></div>
                            <div><div className="text-muted-foreground text-xs">Starting Height</div><div className="mt-1">{(peer.startingheight || 0).toLocaleString()}</div></div>
                          </div>
                        </div>
                      )}
                    </Card>
                  ))}
                </div>
                <div className="hidden overflow-x-auto md:block">
                  <Table>
                    <TableHeader><TableRow><TableHead className="w-12"></TableHead><TableHead>Address</TableHead><TableHead>Version</TableHead><TableHead>Direction</TableHead><TableHead>Ping</TableHead><TableHead>Sync Status</TableHead><TableHead>Ban Score</TableHead><TableHead>Connected</TableHead></TableRow></TableHeader>
                    <TableBody>
                      {peers.map((peer) => (
                        <React.Fragment key={peer.id}>
                          <TableRow className="hover:bg-muted/50 cursor-pointer" onClick={() => onTogglePeerExpansion(peer.id)}>
                            <TableCell><ChevronRight className={cn('text-muted-foreground h-4 w-4 transition-transform', expandedPeers.has(peer.id) && 'rotate-90')} /></TableCell>
                            <TableCell className="font-mono text-sm">{peer.addr}</TableCell>
                            <TableCell>{peer.subver}</TableCell>
                            <TableCell><Badge variant={peer.inbound ? 'secondary' : 'default'}>{peer.inbound ? 'Inbound' : 'Outbound'}</Badge></TableCell>
                            <TableCell className="font-mono text-sm">{formatPingTime(peer.pingtime)}</TableCell>
                            <TableCell><Badge variant={peer.synced_blocks >= (peer.startingheight || 0) ? 'default' : 'secondary'}>{getSyncStatus(peer.synced_blocks || 0, peer.startingheight || 0)}</Badge></TableCell>
                            <TableCell><Badge variant={(peer.banscore || 0) > 0 ? 'destructive' : 'outline'}>{peer.banscore || 0}</Badge></TableCell>
                            <TableCell className="text-muted-foreground text-xs">{peer.conntime ? formatDistanceToNow(new Date(peer.conntime * 1000), { addSuffix: true }) : 'Unknown'}</TableCell>
                          </TableRow>
                          {expandedPeers.has(peer.id) && (
                            <TableRow><TableCell colSpan={8} className="bg-muted/20">
                              <Collapsible open><CollapsibleContent className="px-4 py-3">
                                <div className="space-y-3">
                                  <p className="text-sm font-medium">Detailed Connection Information</p>
                                  <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Local Address:</span><span className="font-mono text-xs">{peer.addrlocal || 'N/A'}</span></div>
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Services:</span><span className="font-mono text-xs">{peer.services || 'N/A'}</span></div>
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Time Offset:</span><span>{peer.timeoffset || 0}s</span></div>
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Min Ping:</span><span>{formatPingTime(peer.minping)}</span></div>
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Starting Height:</span><span>{(peer.startingheight || 0).toLocaleString()}</span></div>
                                    </div>
                                    <div className="space-y-2">
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Last Send:</span><span>{peer.lastsend ? formatDistanceToNow(new Date(peer.lastsend * 1000), { addSuffix: true }) : 'Never'}</span></div>
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Last Receive:</span><span>{peer.lastrecv ? formatDistanceToNow(new Date(peer.lastrecv * 1000), { addSuffix: true }) : 'Never'}</span></div>
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Data Sent:</span><span>{formatBytes(peer.bytessent || 0)}</span></div>
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Data Received:</span><span>{formatBytes(peer.bytesrecv || 0)}</span></div>
                                      <div className="flex justify-between text-sm"><span className="text-muted-foreground">Relay TX:</span><span>{peer.relaytxes ? 'Yes' : 'No'}</span></div>
                                    </div>
                                  </div>
                                  {peer.inflight.length > 0 && (
                                    <div className="mt-3">
                                      <p className="mb-2 text-sm font-medium">Blocks in Flight:</p>
                                      <div className="flex flex-wrap gap-1">{peer.inflight.map((block) => (<Badge key={block} variant="outline" className="text-xs">{block}</Badge>))}</div>
                                    </div>
                                  )}
                                </div>
                              </CollapsibleContent></Collapsible>
                            </TableCell></TableRow>
                          )}
                        </React.Fragment>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </>
            ) : (
              <Empty><EmptyHeader><EmptyMedia variant="icon"><Users /></EmptyMedia><EmptyTitle>No Connected Peers</EmptyTitle><EmptyDescription>There are currently no peers connected to the blockchain network.</EmptyDescription></EmptyHeader></Empty>
            )}
          </CardContent>
        </Card>
      </TabsContent>

      {/* Search Results Tab */}
      <TabsContent value="search">
        {searchResults ? (
          <div className="space-y-6">
            {searchResults.block && (<Card><CardHeader><CardTitle>Block</CardTitle></CardHeader><CardContent><pre className="text-sm whitespace-pre-wrap">{JSON.stringify(searchResults.block, null, 2)}</pre></CardContent></Card>)}
            {searchResults.transaction && (<Card><CardHeader><CardTitle>Transaction</CardTitle></CardHeader><CardContent><pre className="text-sm whitespace-pre-wrap">{JSON.stringify(searchResults.transaction, null, 2)}</pre></CardContent></Card>)}
            {searchResults.address && (<Card><CardHeader><CardTitle>Address</CardTitle></CardHeader><CardContent><pre className="text-sm whitespace-pre-wrap">{JSON.stringify(searchResults.address, null, 2)}</pre></CardContent></Card>)}
            {!searchResults.block && !searchResults.transaction && !searchResults.address && (
              <Card><CardContent><p className="text-muted-foreground">No results found for "{searchQuery}"</p></CardContent></Card>
            )}
          </div>
        ) : (
          <Card><CardContent className="py-12"><Empty><EmptyHeader><EmptyTitle>Search Blockchain</EmptyTitle><EmptyDescription>Enter a block hash, height, transaction ID, or address to search</EmptyDescription></EmptyHeader></Empty></CardContent></Card>
        )}
      </TabsContent>
    </>
  );
}
