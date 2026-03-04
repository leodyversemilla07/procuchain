import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { notifications as notificationsPage } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { locked as adminAccountsLocked } from '@/routes/admin/accounts';
import adminAuditLog from '@/routes/admin/audit-log';
import adminBlockchain from '@/routes/admin/blockchain';
import { index as adminInvitations } from '@/routes/admin/invitations';
import adminLoginLogs from '@/routes/admin/login-logs';
import { index as adminProcurementsList } from '@/routes/admin/procurements';
import { index as stageDocumentsIndex } from '@/routes/admin/stage-documents';
import adminUsers from '@/routes/admin/users';
import { index as workflowConfigIndex, preview as workflowPreview } from '@/routes/admin/workflow-config';
import { dashboard as bacChairmanDashboard } from '@/routes/bac-chairman';
import { index as bacChairmanProcurementsList } from '@/routes/bac-chairman/procurements';
import { dashboard as bacSecretariatDashboard } from '@/routes/bac-secretariat';
import { index as bacSecretariatProcurementInitiation } from '@/routes/bac-secretariat/procurement/initiation';
import { index as bacSecretariatProcurementsList } from '@/routes/bac-secretariat/procurements';
import { dashboard as hopeDashboard } from '@/routes/hope';
import { index as hopeProcurementsList } from '@/routes/hope/procurements';
import { index as reportsIndex } from '@/routes/reports';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    Blocks,
    Eye,
    FileText,
    GitBranch,
    LayoutGrid,
    Mail,
    ScrollText,
    Shield,
    ShieldOff,
    Table2,
    Upload,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

const getNavItemsByRole = (role: string, permissions: ReturnType<typeof usePermissions>): NavItem[] => {
    const { can, hasPermission } = permissions;

    const items: NavItem[] = [];

    // Dashboard - role-based
    switch (role) {
        case 'admin':
            if (hasPermission('view admin dashboard')) {
                items.push({
                    title: 'Dashboard',
                    href: adminDashboard.url(),
                    icon: LayoutGrid,
                });
            }
            break;
        case 'bac_secretariat':
            if (hasPermission('view bac-secretariat dashboard')) {
                items.push({
                    title: 'Dashboard',
                    href: bacSecretariatDashboard.url(),
                    icon: LayoutGrid,
                });
            }
            break;
        case 'bac_chairman':
            if (hasPermission('view bac-chairman dashboard')) {
                items.push({
                    title: 'Dashboard',
                    href: bacChairmanDashboard.url(),
                    icon: LayoutGrid,
                });
            }
            break;
        case 'hope':
            if (hasPermission('view hope dashboard')) {
                items.push({
                    title: 'Dashboard',
                    href: hopeDashboard.url(),
                    icon: LayoutGrid,
                });
            }
            break;
    }

    // Procurement List - available to all roles with view procurement permission
    if (hasPermission('view procurement')) {
        let procurementListUrl = '';
        switch (role) {
            case 'admin':
                procurementListUrl = adminProcurementsList.url();
                break;
            case 'bac_secretariat':
                procurementListUrl = bacSecretariatProcurementsList.url();
                break;
            case 'bac_chairman':
                procurementListUrl = bacChairmanProcurementsList.url();
                break;
            case 'hope':
                procurementListUrl = hopeProcurementsList.url();
                break;
        }

        if (procurementListUrl) {
            items.push({
                title: 'Procurement List',
                href: procurementListUrl,
                icon: Table2,
            });
        }
    }

    // Reports - available to all authenticated users
    items.push({
        title: 'Reports',
        href: reportsIndex().url,
        icon: BarChart3,
    });

    // Procurement Initiation - only for BAC Secretariat with permission
    if (role === 'bac_secretariat' && can.manageProcurement) {
        items.push({
            title: 'Procurement Initiation',
            href: bacSecretariatProcurementInitiation.url(),
            icon: Upload,
        });
    }

    // User Management - only for admins with permission
    if (can.manageUsers) {
        items.push({
            title: 'User Management',
            href: adminUsers.index.url(),
            icon: Users,
        });

        items.push({
            title: 'User Invitations',
            href: adminInvitations().url,
            icon: Mail,
        });
    }

    // Locked Accounts - only for admins
    if (role === 'admin') {
        items.push({
            title: 'Locked Accounts',
            href: adminAccountsLocked.url(),
            icon: ShieldOff,
        });

        items.push({
            title: 'Login Logs',
            href: adminLoginLogs.index.url(),
            icon: Shield,
        });

        items.push({
            title: 'Audit Log',
            href: adminAuditLog.index.url(),
            icon: ScrollText,
        });

        items.push({
            title: 'Blockchain Explorer',
            href: adminBlockchain.explorer.index.url(),
            icon: Blocks,
        });

        // Procurement Configuration (Workflow & Documents)
        items.push({
            title: 'Workflow Config',
            href: workflowConfigIndex().url,
            icon: GitBranch,
        });

        items.push({
            title: 'Workflow Preview',
            href: workflowPreview('competitive_bidding').url,
            icon: Eye,
        });

        items.push({
            title: 'Stage Documents',
            href: stageDocumentsIndex().url,
            icon: FileText,
        });
    }

    return items;
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
    const permissions = usePermissions();

    // Handle case when user is not authenticated
    if (!auth?.user) {
        return null;
    }

    // Get primary role from Spatie roles array
    const primaryRole = auth.roles?.[0] || '';

    const mainNavItems = getNavItemsByRole(primaryRole, permissions);
    const footerNavItems = getFooterNavItemsByRole(primaryRole);

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={getRoleUrl(primaryRole)} prefetch>
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
