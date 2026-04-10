import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

const normalizePathname = (url: string): string => {
    const [pathWithNoQuery] = url.split('?');
    const [pathWithNoHash] = pathWithNoQuery.split('#');
    if (pathWithNoHash.length > 1 && pathWithNoHash.endsWith('/')) {
        return pathWithNoHash.slice(0, -1);
    }

    return pathWithNoHash;
};

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    const currentPath = normalizePathname(page.url);

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu className="gap-1">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            isActive={normalizePathname(item.href) === currentPath}
                            render={<Link href={item.href} prefetch="hover" cacheFor="1m" />}
                        >
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
