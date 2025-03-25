import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, LayoutGrid, Settings, Table2, Upload } from 'lucide-react';
import AppLogo from './app-logo';

const getNavItemsByRole = (role: string): NavItem[] => {
    switch (role) {
        case 'bac_secretariat':
            return [
                {
                    title: 'Dashboard',
                    url: '/bac-secretariat/dashboard',
                    icon: LayoutGrid,
                },
                {
                    title: 'Procurement List',
                    url: '/bac-secretariat/procurements-list',
                    icon: Table2,
                },
                {
                    title: 'Purchase Request Initiation',
                    url: '/bac-secretariat/procurement/pr-initiation',
                    icon: Upload,
                }
            ];
        case 'bac_chairman':
            return [
                {
                    title: 'Dashboard',
                    url: '/bac-chairman/dashboard',
                    icon: LayoutGrid,
                },
                {
                    title: 'Procurement List',
                    url: '/bac-chairman/procurements-list',
                    icon: Table2,
                }
            ];
        case 'hope':
            return [
                {
                    title: 'Dashboard',
                    url: '/hope/dashboard',
                    icon: LayoutGrid,
                },
                {
                    title: 'Procurement List',
                    url: '/hope/procurements-list',
                    icon: Table2,
                }
            ];
        default:
            return [];
    }
};

const footerNavItems: NavItem[] = [
    {
        title: 'Settings',
        url: '/settings',
        icon: Settings,
    },
    {
        title: 'Help',
        url: '/help',
        icon: BookOpen,
    }
];

const getRoleUrl = (role: string): string => {
    switch (role) {
        case 'bac_secretariat':
            return '/bac-secretariat/dashboard';
        case 'bac_chairman':
            return '/bac-chairman/dashboard';
        case 'hope':
            return '/hope/dashboard';
        default:
            return '/';
    }
};

export function AppSidebar() {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const mainNavItems = getNavItemsByRole(auth.user.role);

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={getRoleUrl(auth.user.role)} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
