<template>
  <PageWrapper title="OAuth Client" subtitle="管理应用的 OAuth/OIDC client、redirect URI、scope、grant、secret 与签名 key">
    <div class="mb-4 flex flex-wrap gap-2"><el-input v-model="keyword" class="max-w-80" placeholder="搜索名称或 Client ID" clearable @keyup.enter="load"/><el-button type="primary" @click="load">搜索</el-button><el-button type="success" @click="openCreate">新建 Client</el-button><el-button @click="openKeys">签名 Key</el-button></div>
    <el-table v-loading="loading" :data="clients"><el-table-column prop="name" label="名称"/><el-table-column prop="clientId" label="Client ID" min-width="260"/><el-table-column prop="clientType" label="类型"/><el-table-column prop="status" label="状态"/><el-table-column label="操作" width="330"><template #default="scope"><el-button @click="edit(scope.row as OAuthClient)">配置</el-button><el-button :disabled="scope.row.clientType === 'public' || scope.row.status !== 'active'" @click="createSecret(scope.row as OAuthClient)">创建 Secret</el-button><el-button type="warning" :disabled="scope.row.status !== 'active'" @click="disable(scope.row as OAuthClient)">禁用</el-button><el-button type="danger" @click="remove(scope.row as OAuthClient)">删除</el-button></template></el-table-column></el-table>
    <el-dialog v-model="editorVisible" :title="form.id ? '配置 OAuth Client' : '新建 OAuth Client'" width="720px"><el-form label-width="120"><el-form-item label="应用 ID"><el-input-number v-model="form.applicationId" :min="1" :disabled="Boolean(form.id)"/></el-form-item><el-form-item label="名称"><el-input v-model="form.name"/></el-form-item><el-form-item label="类型"><el-select v-model="form.clientType"><el-option label="Public" value="public"/><el-option label="Confidential" value="confidential"/><el-option label="Machine" value="machine"/></el-select></el-form-item><el-form-item label="Grant"><el-checkbox-group v-model="form.grants"><el-checkbox value="authorization_code">authorization_code</el-checkbox><el-checkbox value="refresh_token">refresh_token</el-checkbox><el-checkbox value="client_credentials">client_credentials</el-checkbox></el-checkbox-group></el-form-item><el-form-item label="Scope"><el-select v-model="form.scopes" multiple allow-create filterable><el-option v-for="scope in builtinScopes" :key="scope" :label="scope" :value="scope"/></el-select></el-form-item><el-form-item label="Redirect URI"><div class="w-full"><div v-for="(uri,index) in form.redirectUris" :key="index" class="mb-2 flex gap-2"><el-select v-model="uri.uriType" class="w-48"><el-option label="授权回调" value="authorization_callback"/><el-option label="退出回调" value="post_logout"/></el-select><el-input v-model="uri.redirectUri" placeholder="https://example.com/callback"/><el-button @click="form.redirectUris.splice(index,1)">移除</el-button></div><el-button @click="addRedirect">添加 URI</el-button></div></el-form-item></el-form><template #footer><el-button @click="editorVisible=false">取消</el-button><el-button type="primary" @click="save">保存</el-button></template></el-dialog>
    <el-dialog v-model="secretVisible" title="Client Secret（仅显示一次）" width="620px" :close-on-click-modal="false" @closed="clearRevealedSecret"><el-alert title="请立即复制并安全保存，关闭后无法恢复。系统不会在 URL、日志或本地存储中保存明文。" type="warning" :closable="false"/><el-input class="mt-4" :model-value="revealedSecret" readonly show-password/><template #footer><el-button type="primary" @click="secretVisible=false">我已保存并关闭</el-button></template></el-dialog>
    <el-drawer v-model="keysVisible" title="OIDC 签名 Key" size="620px"><el-button type="primary" class="mb-4" @click="rotateKey">轮换 Key</el-button><el-table :data="keys"><el-table-column prop="kid" label="KID"/><el-table-column prop="status" label="状态"/><el-table-column prop="publishUntil" label="旧 Key 发布至"/></el-table></el-drawer>
  </PageWrapper>
</template>
<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { oauthClientApi, signingKeyApi, type OAuthClient, type OAuthClientInput, type OAuthClientType, type OAuthGrant, type OAuthRedirectUri, type SigningKey } from '@/api/identity/oauthClients';
defineOptions({ name: 'OAuthClientManagement' });
const route = useRoute();
const builtinScopes = ['openid','profile','email','phone','offline_access'];
const loading=ref(false), keyword=ref(''), editorVisible=ref(false), secretVisible=ref(false), keysVisible=ref(false), revealedSecret=ref('');
const clients=ref<OAuthClient[]>([]), keys=ref<SigningKey[]>([]);
const selectedApplicationId=()=>Math.max(0,Number(route.query.applicationId||0));
const form=reactive<{id:number;applicationId:number;name:string;clientType:OAuthClientType;grants:OAuthGrant[];scopes:string[];redirectUris:OAuthRedirectUri[]}>({id:0,applicationId:selectedApplicationId()||1,name:'',clientType:'confidential',grants:['authorization_code'],scopes:['openid'],redirectUris:[]});
const load=async()=>{loading.value=true;try{clients.value=(await oauthClientApi.list({page:1,pageSize:100,keyword:keyword.value,applicationId:selectedApplicationId()||undefined})).list;}finally{loading.value=false;}};
const reset=()=>Object.assign(form,{id:0,applicationId:selectedApplicationId()||1,name:'',clientType:'confidential',grants:['authorization_code'],scopes:['openid'],redirectUris:[]});
const openCreate=()=>{reset();editorVisible.value=true;};
const edit=(client:OAuthClient)=>{Object.assign(form,{...client,redirectUris:client.redirectUris.map((uri)=>({...uri}))});editorVisible.value=true;};
const addRedirect=()=>form.redirectUris.push({uriType:'authorization_callback',redirectUri:''});
const validateUris=()=>form.redirectUris.every(({redirectUri})=>{try{const url=new URL(redirectUri);return url.protocol==='https:'&&!url.username&&!url.password&&!url.hash&&!redirectUri.includes('*');}catch{return false;}});
const save=async()=>{if(!validateUris()){ElMessage.error('Redirect URI 必须为无用户信息、fragment、通配符的 HTTPS 绝对地址');return;}const input:OAuthClientInput={applicationId:form.applicationId,name:form.name,clientType:form.clientType,grants:form.grants,scopes:form.scopes};const saved=form.id?await oauthClientApi.update(form.id,input):await oauthClientApi.create(input);await oauthClientApi.replaceRedirectUris(saved.id,form.redirectUris);editorVisible.value=false;await load();};
const createSecret=async(client:OAuthClient)=>{const result=await oauthClientApi.createSecret(client.id);revealedSecret.value=result.secret;secretVisible.value=true;};
const clearRevealedSecret=()=>{revealedSecret.value='';};
const disable=async(client:OAuthClient)=>{await oauthClientApi.disable(client.id);await load();};
const remove=async(client:OAuthClient)=>{await ElMessageBox.confirm(`确定删除 ${client.name} 吗？`,'确认');await oauthClientApi.remove(client.id);await load();};
const openKeys=async()=>{keys.value=await signingKeyApi.list();keysVisible.value=true;};
const rotateKey=async()=>{await ElMessageBox.confirm('轮换后旧 Key 将在发布窗口内继续出现在 JWKS 中，确定继续？','签名 Key 轮换');await signingKeyApi.rotate();keys.value=await signingKeyApi.list();};
onMounted(load);
</script>
