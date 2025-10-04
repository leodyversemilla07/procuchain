import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell, LayoutGrid, Shield, ShieldOff, Table2, Upload, Users } from 'lucide-react';
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
                    title: 'Procurement Initiation',
                    url: '/bac-secretariat/procurement/procurement-initiation',
                    icon: Upload,
                },
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
                },
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
                },
            ];
        case 'admin':
            return [
                {
                    title: 'Dashboard',
                    url: '/admin/dashboard',
                    icon: LayoutGrid,
                },
                {
                    title: 'Procurement List',
                    url: '/admin/procurements-list',
                    icon: Table2,
                },
                {
                    title: 'User Management',
                    url: '/admin/users',
                    icon: Users,
                },
                {
                    title: 'Locked Accounts',
                    url: '/admin/accounts/locked',
                    icon: ShieldOff,
                },
                {
                    title: 'Login Logs',
                    url: '/admin/login-logs',
                    icon: Shield,
                },
            ];
        default:
            return [];
    }
};

const getFooterNavItemsByRole = (role: string): NavItem[] => {
    if (role === 'bac_chairman' || role === 'hope' || role === 'admin') {
        return [
            {
                title: 'Notifications',
                url: '/notifications',
                icon: Bell,
            },
        ];
    }
    return [];
};

const getRoleUrl = (role: string): string => {
    switch (role) {
        case 'bac_secretariat':
            return '/bac-secretariat/dashboard';
        case 'bac_chairman':
            return '/bac-chairman/dashboard';
        case 'hope':
            return '/hope/dashboard';
        case 'admin':
            return '/admin/dashboard';
        default:
            return '/';
    }
};

export function AppSidebar() {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    
    // Handle case when user is not authenticated
    if (!auth?.user) {
        return null;
    }
    
    const mainNavItems = getNavItemsByRole(auth.user.role);
    const footerNavItems = getFooterNavItemsByRole(auth.user.role);

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
