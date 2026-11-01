<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Package, Route, Users, Truck, Building2, FileText } from '@/UI/Icons';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as reportesRuta } from '@/routes/reportes';
import { index as rutasRuta } from '@/routes/rutas';
import { index as empleadosRuta } from '@/routes/empleados';
import { index as enviosRuta } from '@/routes/envios';
import { index as vehiculosRuta } from '@/routes/vehiculos';
import { index as clientes } from '@/routes/clientes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    { title: 'Envíos', href: enviosRuta(), icon: Package },
    { title: 'Rutas', href: rutasRuta(), icon: Route },
    { title: 'Empleados', href: empleadosRuta(), icon: Users },
    { title: 'Vehículos', href: vehiculosRuta(), icon: Truck },
    { title: 'Clientes', href: clientes(), icon: Building2 },
    { title: 'Reportes', href: reportesRuta(), icon: FileText },
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
