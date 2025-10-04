import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { notifications as notificationsPage } from '@/routes';
import { dashboard as adminDashboard, loginLogs as adminLoginLogs, users as adminUsers } from '@/routes/admin';
import { locked as adminAccountsLocked } from '@/routes/admin/accounts';
import { index as adminProcurementsList } from '@/routes/admin/procurements-list';
import { dashboard as bacChairmanDashboard } from '@/routes/bac-chairman';
import { index as bacChairmanProcurementsList } from '@/routes/bac-chairman/procurements-list';
import { dashboard as bacSecretariatDashboard } from '@/routes/bac-secretariat';
import { procurementInitiation as bacSecretariatProcurementInitiation } from '@/routes/bac-secretariat/procurement';
import { index as bacSecretariatProcurementsList } from '@/routes/bac-secretariat/procurements-list';
import { dashboard as hopeDashboard } from '@/routes/hope';
import { index as hopeProcurementsList } from '@/routes/hope/procurements-list';
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
                    href: bacSecretariatDashboard.url(),
                    icon: LayoutGrid,
                },
                {
                    title: 'Procurement List',
                    href: bacSecretariatProcurementsList.url(),
                    icon: Table2,
                },
                {
                    title: 'Procurement Initiation',
                    href: bacSecretariatProcurementInitiation.url(),
                    icon: Upload,
                },
            ];
        case 'bac_chairman':
            return [
                {
                    title: 'Dashboard',
                    href: bacChairmanDashboard.url(),
                    icon: LayoutGrid,
                },
                {
                    title: 'Procurement List',
                    href: bacChairmanProcurementsList.url(),
                    icon: Table2,
                },
            ];
        case 'hope':
            return [
                {
                    title: 'Dashboard',
                    href: hopeDashboard.url(),
                    icon: LayoutGrid,
                },
                {
                    title: 'Procurement List',
                    href: hopeProcurementsList.url(),
                    icon: Table2,
                },
            ];
        case 'admin':
            return [
                {
                    title: 'Dashboard',
                    href: adminDashboard.url(),
                    icon: LayoutGrid,
                },
                {
                    title: 'Procurement List',
                    href: adminProcurementsList.url(),
                    icon: Table2,
                },
                {
                    title: 'User Management',
                    href: adminUsers.url(),
                    icon: Users,
                },
                {
                    title: 'Locked Accounts',
                    href: adminAccountsLocked.url(),
                    icon: ShieldOff,
                },
                {
                    title: 'Login Logs',
                    href: adminLoginLogs.url(),
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
                href: notificationsPage.url(),
                icon: Bell,
            },
        ];
    }
    return [];
};

const getRoleUrl = (role: string): string => {
    switch (role) {
        case 'bac_secretariat':
            return bacSecretariatDashboard.url();
        case 'bac_chairman':
            return bacChairmanDashboard.url();
        case 'hope':
            return hopeDashboard.url();
        case 'admin':
            return adminDashboard.url();
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
