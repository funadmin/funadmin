<template>
  <section>
    <h1>etst</h1>
    <el-button v-perm="'etst:item:list'" @click="load">刷新</el-button>
  </section>
</template>

<script setup lang="ts">
import { listItems } from '../api';

const load = async () => { await listItems(); };
</script>