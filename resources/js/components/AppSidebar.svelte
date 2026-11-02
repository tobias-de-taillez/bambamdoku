<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import CalendarDays from '@lucide/svelte/icons/calendar-days';
    import ClipboardList from '@lucide/svelte/icons/clipboard-list';
    import Target from '@lucide/svelte/icons/target';
    import Users from '@lucide/svelte/icons/users';
    import UsersRound from '@lucide/svelte/icons/users-round';
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
    import { dashboard } from '@/routes';
    import { index as groupsIndex } from '@/routes/admin/groups';
    import { index as bookingsIndex } from '@/routes/bookings';
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
    ];

    const adminNavItems: NavItem[] = [
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
            title: 'Gruppen',
            href: groupsIndex(),
            icon: UsersRound,
        },
    ];

    const footerNavItems: NavItem[] = [];
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
