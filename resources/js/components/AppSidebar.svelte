<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import CalendarDays from '@lucide/svelte/icons/calendar-days';
    import ClipboardList from '@lucide/svelte/icons/clipboard-list';
    import NotebookPen from '@lucide/svelte/icons/notebook-pen';
    import ChartColumn from '@lucide/svelte/icons/chart-column';
    import History from '@lucide/svelte/icons/history';
    import Bug from '@lucide/svelte/icons/bug';
    import Settings from '@lucide/svelte/icons/settings';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import ShieldQuestion from '@lucide/svelte/icons/shield-question';
    import Scale from '@lucide/svelte/icons/scale';
    import CircleHelp from '@lucide/svelte/icons/circle-help';
    import Target from '@lucide/svelte/icons/target';
    import Users from '@lucide/svelte/icons/users';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavFooter from '@/components/NavFooter.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard, help, imprint, privacy } from '@/routes';
    import { index as bookingsIndex } from '@/routes/bookings';
    import { show as rangeOffice } from '@/routes/range-office';
    import { index as recordIndex } from '@/routes/record';
    import { index as adminRecordIndex } from '@/routes/admin/record';
    import { edit as settingsEdit } from '@/routes/admin/settings';
    import { index as auditIndex } from '@/routes/admin/audit';
    import { index as helpRequestsIndex } from '@/routes/admin/help-requests';
    import { statistics } from '@/routes/admin';
    import { index as memberDaysIndex } from '@/routes/shooting-days';
    import { index as rangesIndex } from '@/routes/admin/ranges';
    import { index as shootingDaysIndex } from '@/routes/admin/shooting-days';
    import { index as usersIndex } from '@/routes/admin/users';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const rangeOfficeItems: NavItem[] = [
        {
            title: 'Am Stand',
            href: rangeOffice(),
            icon: ShieldCheck,
        },
    ];

    const mainNavItems: NavItem[] = [
        {
            title: 'Übersicht',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Schießtage',
            href: memberDaysIndex(),
            icon: CalendarDays,
        },
        {
            title: 'Meine Buchungen',
            href: bookingsIndex(),
            icon: ClipboardList,
        },
        {
            title: 'Schießkladde',
            href: recordIndex(),
            icon: NotebookPen,
        },
    ];

    const adminNavItems: NavItem[] = [
        {
            title: 'Kladde des Vereins',
            href: adminRecordIndex(),
            icon: NotebookPen,
        },
        {
            title: 'Termine planen',
            href: shootingDaysIndex(),
            icon: CalendarDays,
        },
        {
            title: 'Stände',
            href: rangesIndex(),
            icon: Target,
        },
        {
            title: 'Mitglieder',
            href: usersIndex(),
            icon: Users,
        },
        {
            title: 'Statistik',
            href: statistics(),
            icon: ChartColumn,
        },
        {
            title: 'Protokoll',
            href: auditIndex(),
            icon: History,
        },
        {
            title: 'Rückmeldungen',
            href: helpRequestsIndex(),
            icon: Bug,
        },
        {
            title: 'Einstellungen',
            href: settingsEdit(),
            icon: Settings,
        },
    ];

    const footerNavItems: NavItem[] = $derived([
        {
            title: 'Anleitung',
            href: help(),
            icon: CircleHelp,
        },
        {
            title: 'Datenschutzhinweis',
            href: privacy(),
            icon: ShieldQuestion,
        },
        ...(page.props.hasImprint ? [{ title: 'Impressum', href: imprint(), icon: Scale }] : []),
    ]);
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        {#if page.props.auth.supervisesToday || page.props.auth.isAdmin}
            <NavMain items={rangeOfficeItems} label="Am Stand" />
        {/if}
        <NavMain items={mainNavItems} />
        {#if page.props.auth.isAdmin}
            <NavMain items={adminNavItems} label="Verwaltung" />
        {/if}
    </SidebarContent>

    <SidebarFooter>
        <NavFooter items={footerNavItems} />
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
