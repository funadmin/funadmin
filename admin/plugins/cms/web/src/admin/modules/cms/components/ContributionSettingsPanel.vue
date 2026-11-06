<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { onMounted, ref } from 'vue';
import { cmsApi } from '../api';
import type { ContributionPolicy } from '../types';
import { useOperation } from './useOperation';
const { t } = useCmsI18n();
const props=defineProps<{siteId:string;can:(permission:string)=>boolean}>();
const {busy,error,run}=useOperation();
const enabled=ref(false),modelCodes=ref<string[]>([]),models=ref<ContributionPolicy['models']>([]),ready=ref(false),saved=ref(false);
async function load(){await run(async()=>{const result=await cmsApi.contributionSettings(props.siteId);enabled.value=result.enabled;modelCodes.value=result.model_codes;models.value=result.models;ready.value=true;});}
async function save(){if(!ready.value||busy.value||!props.can('cms:site:edit'))return;saved.value=false;await run(async()=>{const result=await cmsApi.saveContributionSettings({site_id:props.siteId,enabled:enabled.value,model_codes:modelCodes.value});if(result){enabled.value=result.enabled;modelCodes.value=result.model_codes;}saved.value=true;});}
onMounted(()=>void load());
</script>
<template>
 <section class="contribution-settings" v-loading="busy">
  <el-alert v-if="error" :title="error" type="error" :closable="false">{{ error }}</el-alert>
  <el-alert v-if="saved" :title="t('cms.labels.memberInteractionSettingsSaved')" type="success" :closable="false" />
  <el-form v-if="ready" label-position="top">
   <el-form-item :label="t('cms.labels.memberSubmissionsAndQA')"><el-switch v-model="enabled" :disabled="!can('cms:site:edit')" /><p class="hint">{{ t('cms.labels.whenEnabledSignedInMembersCanSubmitContentQuestions') }}</p></el-form-item>
   <el-form-item :label="t('cms.labels.modelsAllowedForSubmissions')"><el-checkbox-group v-model="modelCodes" :disabled="!can('cms:site:edit')"><el-checkbox v-for="model in models" :key="model.code" :value="model.code">{{ model.name }}</el-checkbox></el-checkbox-group><p class="hint">{{ t('cms.labels.onlyEnabledSiteModelsWithoutAttachmentFieldsAreListed') }}</p></el-form-item>
   <el-button v-if="can('cms:site:edit')" type="primary" :loading="busy" @click="save">{{ t('cms.labels.saveMemberInteractionSettings') }}</el-button>
  </el-form>
 </section>
</template>
<style scoped>.hint{width:100%;color:var(--el-text-color-secondary);font-size:13px;line-height:1.8;margin:10px 0}.contribution-settings{max-width:900px}.contribution-settings .el-alert{margin-bottom:18px}</style>
