<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Cpu, History, Settings, ListChecks, MonitorPlay, Scan, ClipboardCheck } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';

const { t } = useI18n();
const { isCurrentUrl } = useCurrentUrl();


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
                                <SidebarMenuButton :tooltip="t('sidebar.nav.onuScan')">
                                    <Cpu />
                                    <span>{{ t('sidebar.nav.onuScan') }}</span>
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
                                                <span>{{ t('sidebar.nav.quickScan') }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child :is-active="isCurrentUrl('/olt/audit-session')">
                                            <Link href="/olt/audit-session">
                                                <ClipboardCheck />
                                                <span>{{ t('sidebar.nav.auditSession') }}</span>
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
                                <SidebarMenuButton :tooltip="t('sidebar.nav.history')">
                                    <History />
                                    <span>{{ t('sidebar.nav.history') }}</span>
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
                                                <span>{{ t('sidebar.nav.action') }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child :is-active="isCurrentUrl('/olt/history/session')">
                                            <Link href="/olt/history/session">
                                                <MonitorPlay />
                                                <span>{{ t('sidebar.nav.scanSession') }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>

                    <!-- OLT Settings -->
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :is-active="isCurrentUrl('/olt/settings')" :tooltip="t('sidebar.nav.oltSettings')">
                            <Link href="/olt/settings">
                                <Settings />
                                <span>{{ t('sidebar.nav.oltSettings') }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
