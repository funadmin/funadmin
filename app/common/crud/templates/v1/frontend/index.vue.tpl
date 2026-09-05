<template>
  <PageWrapper title="{{title}}">
    <div data-crud="{{name}}">M3 CRUD fixture</div>
  </PageWrapper>
</template>

<script setup lang="ts">
import { useCrud } from '@/composables/useCrud';

void useCrud;
const apiPrefix = '{{apiPrefix}}';
const permissionPrefix = '{{permissionPrefix}}';
void apiPrefix;
void permissionPrefix;
</script>
