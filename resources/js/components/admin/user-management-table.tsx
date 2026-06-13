import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import type { User } from '@/types/user';
import { getRoleBadgeColor, getRoleDisplayName } from '@/types/user';
import type { Table as ReactTable } from '@tanstack/react-table';
import { flexRender } from '@tanstack/react-table';
import { Edit, History, KeyRound, MoreHorizontal, QrCode, Trash2, Users } from 'lucide-react';
import { toast } from 'sonner';

interface UserManagementTableProps {
    table: ReactTable<User>;
    isLoading: boolean;
    isRefreshing: boolean;
    filteredUsers: User[];
    totalUsers: number;
    searchQuery: string;
    roleFilter: string;
    verificationFilter: string;
    twoFactorFilter: string;
    onEditUser: (user: User) => void;
    onDeleteUser: (user: User) => void;
    onViewDetails: (user: User) => void;
    onViewLoginHistory: (user: User) => void;
    onResetPassword: (user: User) => void;
    onCreateUser: () => void;
}

export function UserManagementTable({
    table,
    isLoading,
    isRefreshing,
    filteredUsers,
    searchQuery,
    roleFilter,
    verificationFilter,
    twoFactorFilter,
    onEditUser,
    onDeleteUser,
    onViewDetails,
    onViewLoginHistory,
    onResetPassword,
}: UserManagementTableProps) {
    const { hasPermission } = usePermissions();

    return (
        <>
            {filteredUsers.length === 0 && !isLoading && !isRefreshing ? (
                <Card>
                    <CardContent className="flex justify-center px-4 py-8 sm:px-6 sm:py-10 md:py-12">
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Users />
                                </EmptyMedia>
                            </EmptyHeader>
                            <EmptyTitle>No users found</EmptyTitle>
                            <EmptyDescription>
                                {searchQuery || roleFilter !== 'all' || verificationFilter !== 'all' || twoFactorFilter !== 'all'
                                    ? 'Try adjusting your search or filter criteria.'
                                    : 'Click "Add User" to create your first user'}
                            </EmptyDescription>
                        </Empty>
                    </CardContent>
                </Card>
            ) : (
                <Card className="hidden md:block">
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id}>
                                        {headerGroup.headers.map((header) => (
                                            <TableHead key={header.id}>
                                                {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {isLoading || isRefreshing ? (
                                    Array.from({ length: 5 }).map((_, index) => (
                                        <TableRow key={index}>
                                            <TableCell>
                                                <Skeleton />
                                            </TableCell>
                                            <TableCell>
                                                <Skeleton className="h-4 w-32" />
                                            </TableCell>
                                            <TableCell>
                                                <Skeleton className="h-4 w-48" />
                                            </TableCell>
                                            <TableCell>
                                                <Skeleton className="h-5 w-24" />
                                            </TableCell>
                                            <TableCell>
                                                <Skeleton className="h-4 w-40" />
                                            </TableCell>
                                            <TableCell>
                                                <Skeleton className="h-5 w-20" />
                                            </TableCell>
                                            <TableCell>
                                                <Skeleton className="h-5 w-20" />
                                            </TableCell>
                                            <TableCell>
                                                <Skeleton className="h-4 w-24" />
                                            </TableCell>
                                            <TableCell>
                                                <Skeleton className="h-4 w-24" />
                                            </TableCell>
                                            <TableCell>
                                                <Skeleton className="h-8 w-8 rounded" />
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : table.getRowModel().rows?.length ? (
                                    table.getRowModel().rows.map((row) => (
                                        <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                            ))}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={table.getAllColumns().length} className="h-24 text-center">
                                            No results.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                    <CardFooter className="justify-end border-t px-6 py-5">
                        <Pagination
                            pageIndex={table.getState().pagination.pageIndex}
                            pageSize={table.getState().pagination.pageSize}
                            pageCount={table.getPageCount()}
                            totalItems={table.getFilteredRowModel().rows.length}
                            onPageChange={table.setPageIndex}
                            onPageSizeChange={table.setPageSize}
                        />
                    </CardFooter>
                </Card>
            )}

            {/* Mobile Card View */}
            {filteredUsers.length > 0 && (
                <div className="flex flex-col gap-3 md:hidden">
                    <div className="flex flex-col gap-4">
                        {isLoading || isRefreshing
                            ? Array.from({ length: 3 }).map((_, index) => (
                                  <Card key={index}>
                                      <CardContent className="p-4">
                                          <div className="flex flex-col gap-4">
                                              <div className="flex items-start justify-between">
                                                  <div className="flex flex-1 flex-col gap-2">
                                                      <Skeleton className="h-5 w-32" />
                                                      <Skeleton className="h-4 w-48" />
                                                  </div>
                                                  <Skeleton className="h-8 w-8" />
                                              </div>
                                              <div className="flex flex-col gap-2">
                                                  <Skeleton className="h-4 w-full" />
                                                  <Skeleton className="h-4 w-full" />
                                                  <Skeleton className="h-4 w-2/3" />
                                              </div>
                                          </div>
                                      </CardContent>
                                  </Card>
                              ))
                            : table.getRowModel().rows.map((row) => {
                                  const user = row.original;
                                  return (
                                      <Card key={user.id}>
                                          <CardHeader className="p-3 pb-2 sm:px-4 sm:py-3">
                                              <div className="flex items-start justify-between gap-2">
                                                  <div className="flex min-w-0 flex-1 items-start gap-2">
                                                      <div className="flex h-9 w-9 shrink-0 items-center justify-center">
                                                          <Checkbox
                                                              checked={row.getIsSelected()}
                                                              onCheckedChange={(value) => row.toggleSelected(!!value)}
                                                          />
                                                      </div>
                                                      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                                          <CardTitle className="truncate text-sm leading-tight">{user.name}</CardTitle>
                                                          <p className="text-muted-foreground truncate text-xs">{user.email}</p>
                                                      </div>
                                                  </div>
                                                  <DropdownMenu>
                                                      <DropdownMenuTrigger
                                                          render={<Button variant="ghost" size="icon" className="size-10 md:size-8" />}
                                                      >
                                                          <MoreHorizontal />
                                                      </DropdownMenuTrigger>
                                                      <DropdownMenuContent align="end">
                                                          <DropdownMenuGroup>
                                                              <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                              <DropdownMenuItem
                                                                  onClick={async () => {
                                                                      try {
                                                                          await navigator.clipboard.writeText(user.email);
                                                                          toast.success('Email copied');
                                                                      } catch {
                                                                          toast.error('Failed to copy');
                                                                      }
                                                                  }}
                                                              >
                                                                  Copy email
                                                              </DropdownMenuItem>
                                                              <DropdownMenuItem onClick={() => onViewDetails(user)}>View Details</DropdownMenuItem>
                                                          </DropdownMenuGroup>
                                                          <DropdownMenuSeparator />
                                                          <DropdownMenuGroup>
                                                              <DropdownMenuItem onClick={() => onViewLoginHistory(user)}>
                                                                  <History />
                                                                  Login History
                                                              </DropdownMenuItem>
                                                              <DropdownMenuItem onClick={() => onResetPassword(user)}>
                                                                  <KeyRound />
                                                                  Reset Password
                                                              </DropdownMenuItem>
                                                          </DropdownMenuGroup>
                                                          <DropdownMenuSeparator />
                                                          <DropdownMenuGroup>
                                                              {hasPermission('edit users') && (
                                                                  <DropdownMenuItem onClick={() => onEditUser(user)}>
                                                                      <Edit />
                                                                      Edit user
                                                                  </DropdownMenuItem>
                                                              )}
                                                              {hasPermission('delete users') && (
                                                                  <DropdownMenuItem onClick={() => onDeleteUser(user)} variant="destructive">
                                                                      <Trash2 />
                                                                      Delete user
                                                                  </DropdownMenuItem>
                                                              )}
                                                          </DropdownMenuGroup>
                                                      </DropdownMenuContent>
                                                  </DropdownMenu>
                                              </div>
                                          </CardHeader>
                                          <CardContent className="flex flex-col gap-2.5 p-3 pt-0 sm:px-4">
                                              <div className="flex items-center justify-between text-xs sm:text-sm">
                                                  <span className="text-muted-foreground">Role</span>
                                                  <Badge className={getRoleBadgeColor(user.role)}>{getRoleDisplayName(user.role)}</Badge>
                                              </div>
                                              <div className="flex items-center justify-between text-xs sm:text-sm">
                                                  <span className="text-muted-foreground">Email Status</span>
                                                  {user.email_verified_at ? (
                                                      <Badge className="bg-primary/20 text-primary dark:bg-primary/20/20 dark:text-primary border border-green-200 text-xs dark:border-green-800/30">
                                                          Verified
                                                      </Badge>
                                                  ) : (
                                                      <Badge className="bg-muted text-muted-foreground dark:bg-muted/20 dark:text-muted-foreground border border-yellow-200 text-xs dark:border-yellow-800/30">
                                                          Pending
                                                      </Badge>
                                                  )}
                                              </div>
                                              <div className="flex items-center justify-between text-xs sm:text-sm">
                                                  <span className="text-muted-foreground">2FA Status</span>
                                                  {user.two_factor_enabled ? (
                                                      <div className="flex items-center gap-1.5">
                                                          <Badge className="bg-primary/20 text-primary dark:bg-primary/20/20 dark:text-primary border border-green-200 text-xs dark:border-green-800/30">
                                                              <QrCode className="mr-1 h-3 w-3" />
                                                              On
                                                          </Badge>
                                                          {user.backup_codes && user.backup_codes.length > 0 && (
                                                              <span className="text-muted-foreground text-[10px]">({user.backup_codes.length})</span>
                                                          )}
                                                      </div>
                                                  ) : (
                                                      <Badge className="border-border bg-muted text-foreground dark:border-border dark:bg-muted/50 dark:text-muted-foreground border text-xs">
                                                          Off
                                                      </Badge>
                                                  )}
                                              </div>
                                              {user.blockchain_address && (
                                                  <div className="bg-muted/50 rounded p-2">
                                                      <div className="text-muted-foreground mb-1 text-[10px] font-medium tracking-wide uppercase">
                                                          Blockchain
                                                      </div>
                                                      <div className="font-mono text-[10px] leading-relaxed break-all">{user.blockchain_address}</div>
                                                  </div>
                                              )}
                                          </CardContent>
                                      </Card>
                                  );
                              })}
                    </div>
                    <div className="mt-4 flex justify-center">
                        <Pagination
                            pageIndex={table.getState().pagination.pageIndex}
                            pageSize={table.getState().pagination.pageSize}
                            pageCount={table.getPageCount()}
                            totalItems={table.getFilteredRowModel().rows.length}
                            onPageChange={table.setPageIndex}
                            onPageSizeChange={table.setPageSize}
                        />
                    </div>
                </div>
            )}
        </>
    );
}
