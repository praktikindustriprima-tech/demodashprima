<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, Cpu, FolderGit2, LayoutGrid, History, Settings, ListChecks, MonitorPlay, Scan, ClipboardCheck } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { dashboard } from '@/routes';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const { isCurrentUrl } = useCurrentUrl();

const footerNavItems = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <!-- ONU Scan (collapsible) -->
                    <Collapsible default-open as-child class="group/collapsible">
                        <SidebarMenuItem>
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton tooltip="ONU Scan">
                                    <Cpu />
                                    <span>ONU Scan</span>
                                    <svg class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m9 18 6-6-6-6"/>
                                    </svg>
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child :is-active="isCurrentUrl('/olt/onu-scan')">
                                            <Link href="/olt/onu-scan">
                                                <Scan />
                                                <span>Quick Scan</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child :is-active="isCurrentUrl('/olt/audit-session')">
                                            <Link href="/olt/audit-session">
                                                <ClipboardCheck />
                                                <span>Audit Session</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>

                    <!-- History (collapsible) -->
                    <Collapsible default-open as-child class="group/collapsible">
                        <SidebarMenuItem>
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton tooltip="History">
                                    <History />
                                    <span>History</span>
                                    <svg class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m9 18 6-6-6-6"/>
                                    </svg>
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child :is-active="isCurrentUrl('/olt/history/action')">
                                            <Link href="/olt/history/action">
                                                <ListChecks />
                                                <span>Action</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child :is-active="isCurrentUrl('/olt/history/session')">
                                            <Link href="/olt/history/session">
                                                <MonitorPlay />
                                                <span>Scan Session</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>

                    <!-- OLT Settings -->
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isCurrentUrl('/olt/settings')" tooltip="OLT Settings">
                            <Link href="/olt/settings">
                                <Settings />
                                <span>OLT Settings</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
