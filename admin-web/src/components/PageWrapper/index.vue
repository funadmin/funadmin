<template>
  <div class="flex min-h-full flex-col gap-0 box-border">
    <main class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-app-card">
      <header
        v-if="$slots.header || $slots.extra"
        class="flex shrink-0 items-center justify-between gap-3 px-4 py-3"
      >
        <div v-if="$slots.header" class="flex items-center gap-0">
          <slot name="header" />
        </div>
        <div v-if="$slots.extra" class="ml-auto flex shrink-0 items-center gap-2">
          <slot name="extra" />
        </div>
      </header>

      <div class="min-h-0 min-w-0 flex-1 overflow-auto px-4 pb-3" :class="{ 'pt-3': !$slots.header && !$slots.extra }">
        <slot />
      </div>
    </main>

    <footer v-if="$slots.footer" class="rounded-lg bg-app-card px-4 py-3">
      <slot name="footer" />
    </footer>
  </div>
</template>

<script setup lang="ts">
interface Props {
  title?: string;
  subtitle?: string;
}
defineProps<Props>();
</script>
