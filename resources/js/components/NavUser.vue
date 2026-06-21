<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);
const isGuest = computed(() => !user.value);
const displayUser = computed(() => user.value || { name: t('navUser.guest'), email: t('navUser.guestHint') });
const { isMobile, state } = useSidebar();
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem v-if="isGuest">
            <SidebarMenuButton as-child size="lg">
                <Link href="/login" class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted">
                        <LogOut class="h-4 w-4 rotate-180" />
                    </div>
                    <div class="grid flex-1 text-left text-sm leading-tight">
                        <span class="truncate font-medium">{{ t('navUser.guestMode') }}</span>
                        <span class="truncate text-xs text-muted-foreground">{{ t('navUser.clickToLogin') }}</span>
                    </div>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
        <SidebarMenuItem v-else>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="displayUser" />
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="displayUser" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
