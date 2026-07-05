<template>
    <router-view v-slot="{ Component }">
        <transition name="fd-fade" mode="out-in">
            <component :is="Component" />
        </transition>
    </router-view>
</template>

<script setup>
// Root of the Flight Dispute Vue island. Screens are provided by the router.
</script>

<style>
.fd-fade-enter-active,
.fd-fade-leave-active {
    transition: opacity 0.15s ease;
}
.fd-fade-enter-from,
.fd-fade-leave-to {
    opacity: 0;
}
</style>
