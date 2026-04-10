import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/settings/profile';
import { type User } from '@/types';
import { Link } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
    const cleanup = useMobileNavigation();

    return (
        <>
            <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <UserInfo user={user} showEmail={true} />
            </div>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem render={<Link className="block w-full" href={edit.url()} as="button" prefetch onClick={cleanup} />}>
                    <Settings className="mr-2" />
                    Settings
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem render={<Link className="block w-full" method="post" href={logout.url()} as="button" onClick={cleanup} />}>
                <LogOut className="mr-2" />
                Log out
            </DropdownMenuItem>
        </>
    );
}
