<template>
  <div v-loading="loading" class="settings">
    <section class="settings-card">
      <header><h3>发布签名密钥</h3><el-tag :type="status?.keySource === 'none' ? 'danger' : 'success'" effect="light" round>{{ sourceLabel }}</el-tag></header>
      <p class="settings-desc">所有版本在上传时用 Ed25519 私钥签名，客户端用对应公钥校验下载的插件包。私钥保存在插件私有存储（runtime 目录）中，或通过环境变量 <code>MARKET_SIGNING_SECRET_KEY</code> 提供；不会随插件包导出。</p>
      <el-alert v-if="status && !status.sodium" type="error" show-icon :closable="false" title="服务器未启用 PHP sodium 扩展，无法签名" />
      <template v-else-if="status?.keySource === 'none'">
        <el-button type="primary" :loading="generating" @click="generate"><i class="i-ep-key" />生成签名密钥</el-button>
      </template>
      <div v-else-if="status" class="key-box">
        <label>公钥（Base64）</label>
        <div class="copy-line"><code>{{ status.publicKey }}</code><el-button link type="primary" @click="copy(status.publicKey)">复制</el-button></div>
        <small>更换密钥会让所有已发布版本在客户端验签失败，需要重新上传签名；因此这里不提供一键重置。</small>
      </div>
    </section>

    <section v-if="payment" class="settings-card">
      <header><h3>在线支付</h3><el-tag :type="enabledChannels ? 'success' : 'info'" effect="light" round>{{ enabledChannels ? `已启用 ${enabledChannels}` : '未启用' }}</el-tag></header>
      <p class="settings-desc">私钥与 APIv3 密钥保存在插件私有存储中（权限 600），保存后不再回显；留空表示保持原值。回调地址必须是公网 HTTPS，请在支付平台商户后台配置。</p>
      <el-form label-position="top" class="pay-form">
        <div class="pay-channel">
          <div class="pay-channel__head"><strong>支付宝 · 电脑网站支付</strong><el-switch v-model="payForm.alipay.enabled" /></div>
          <div class="pay-grid">
            <el-form-item label="APPID"><el-input v-model="payForm.alipay.app_id" /></el-form-item>
            <el-form-item label="环境"><el-radio-group v-model="payForm.alipay.sandbox"><el-radio-button :value="false">正式</el-radio-button><el-radio-button :value="true">沙箱</el-radio-button></el-radio-group></el-form-item>
            <el-form-item class="pay-wide" :label="`应用私钥（RSA2）${payment.alipay.private_key_set ? ' · 已设置' : ''}`"><el-input v-model="payForm.alipay.private_key" type="textarea" :rows="3" :placeholder="payment.alipay.private_key_set ? '留空保持原值' : '粘贴应用私钥'" /></el-form-item>
            <el-form-item class="pay-wide" label="支付宝公钥"><el-input v-model="payForm.alipay.alipay_public_key" type="textarea" :rows="3" placeholder="在开放平台「接口加签方式」中获取支付宝公钥" /></el-form-item>
            <el-form-item class="pay-wide" label="异步通知地址"><code class="notify">{{ payment.notifyUrls.alipay }}</code></el-form-item>
          </div>
        </div>
        <div class="pay-channel">
          <div class="pay-channel__head"><strong>微信支付 · Native 扫码（APIv3）</strong><el-switch v-model="payForm.wechat.enabled" /></div>
          <div class="pay-grid">
            <el-form-item label="商户号 mchid"><el-input v-model="payForm.wechat.mch_id" /></el-form-item>
            <el-form-item label="关联 AppID"><el-input v-model="payForm.wechat.app_id" /></el-form-item>
            <el-form-item label="商户 API 证书序列号"><el-input v-model="payForm.wechat.serial_no" /></el-form-item>
            <el-form-item :label="`APIv3 密钥${payment.wechat.api_v3_key_set ? ' · 已设置' : ''}`"><el-input v-model="payForm.wechat.api_v3_key" type="password" show-password maxlength="32" :placeholder="payment.wechat.api_v3_key_set ? '留空保持原值' : '32 位'" /></el-form-item>
            <el-form-item class="pay-wide" :label="`商户 API 私钥（apiclient_key.pem）${payment.wechat.private_key_set ? ' · 已设置' : ''}`"><el-input v-model="payForm.wechat.private_key" type="textarea" :rows="3" :placeholder="payment.wechat.private_key_set ? '留空保持原值' : '粘贴 PEM 私钥'" /></el-form-item>
            <el-form-item label="微信支付公钥 ID"><el-input v-model="payForm.wechat.platform_public_key_id" placeholder="PUB_KEY_ID_..." /></el-form-item>
            <el-form-item label="微信支付公钥"><el-input v-model="payForm.wechat.platform_public_key" type="textarea" :rows="2" placeholder="商户平台下载的 pub_key.pem" /></el-form-item>
            <el-form-item class="pay-wide" label="异步通知地址"><code class="notify">{{ payment.notifyUrls.wechat }}</code></el-form-item>
          </div>
        </div>
        <div v-if="payment.mock.allowed" class="pay-channel">
          <div class="pay-channel__head"><strong>模拟支付（仅开发环境）</strong><el-switch v-model="payForm.mock.enabled" /></div>
          <p class="settings-desc">APP_DEBUG 开启时可用，买家在收银台点击即视为支付成功，用于联调下单、授权与安装流程。生产环境关闭调试后自动失效。</p>
        </div>
        <div><el-button type="primary" :loading="savingPayment" @click="savePayment">保存支付配置</el-button></div>
      </el-form>
    </section>

    <section v-if="status" class="settings-card">
      <header><h3>客户端接入</h3><el-tag :type="status.https ? 'success' : 'warning'" effect="light" round>{{ status.https ? 'HTTPS' : '非 HTTPS' }}</el-tag></header>
      <p class="settings-desc">在需要从本市场安装插件的 FunAdmin 站点的 <code>.env</code> 中加入以下配置，然后在「插件中心 → 市场账号」用本站会员账号登录，即可在「云市场」中搜索、安装和升级插件；卸载在客户端本地完成。</p>
      <div class="env-block">
        <pre>{{ envSnippet }}</pre>
        <el-button size="small" :disabled="status.keySource === 'none'" @click="copy(envSnippet)"><i class="i-ep-copy-document" />复制配置</el-button>
      </div>
      <el-alert v-if="!status.https" type="warning" show-icon :closable="false" title="客户端只从 HTTPS 公网地址下载插件包" description="请在插件中心 → 插件市场服务端 → 配置 中把「市场对外地址」设置为 https:// 开头的公网地址。" />
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { marketApi, type MarketSigning, type PaymentSettings } from '../api';

const emit = defineEmits<{ changed: [] }>();
const loading = ref(false);
const generating = ref(false);
const status = ref<MarketSigning | null>(null);

const sourceLabel = computed(() => ({ env: '环境变量', file: '已生成', none: '未配置' })[status.value?.keySource ?? 'none']);
const envSnippet = computed(() => status.value
  ? `PLUGIN_MARKETPLACE_URL = ${status.value.publicUrl}\nPLUGIN_MARKETPLACE_PUBLIC_KEY = ${status.value.publicKey || '<先生成签名密钥>'}`
  : '');

const payment = ref<PaymentSettings | null>(null);
const savingPayment = ref(false);
const payForm = reactive({
  alipay: { enabled: false, sandbox: false, app_id: '', private_key: '', alipay_public_key: '' },
  wechat: { enabled: false, mch_id: '', app_id: '', serial_no: '', private_key: '', api_v3_key: '', platform_public_key_id: '', platform_public_key: '' },
  mock: { enabled: false }
});
const enabledChannels = computed(() => [
  payment.value?.alipay.enabled && '支付宝',
  payment.value?.wechat.enabled && '微信支付',
  payment.value?.mock.enabled && payment.value?.mock.allowed && '模拟支付'
].filter(Boolean).join('、'));

function fillPayment(settings: PaymentSettings) {
  payment.value = settings;
  Object.assign(payForm.alipay, { enabled: settings.alipay.enabled, sandbox: settings.alipay.sandbox, app_id: settings.alipay.app_id, alipay_public_key: settings.alipay.alipay_public_key, private_key: '' });
  Object.assign(payForm.wechat, {
    enabled: settings.wechat.enabled, mch_id: settings.wechat.mch_id, app_id: settings.wechat.app_id, serial_no: settings.wechat.serial_no,
    platform_public_key_id: settings.wechat.platform_public_key_id, platform_public_key: settings.wechat.platform_public_key, private_key: '', api_v3_key: ''
  });
  payForm.mock.enabled = settings.mock.enabled;
}

async function savePayment() {
  savingPayment.value = true;
  try {
    fillPayment(await marketApi.savePayment(JSON.parse(JSON.stringify(payForm))));
    emit('changed');
  } finally { savingPayment.value = false; }
}

async function load() {
  loading.value = true;
  try {
    const [signing, settings] = await Promise.all([marketApi.setting(), marketApi.payment()]);
    status.value = signing;
    fillPayment(settings);
  } finally { loading.value = false; }
}
async function generate() {
  generating.value = true;
  try {
    status.value = await marketApi.generateKey();
    emit('changed');
  } finally { generating.value = false; }
}
async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text);
    ElMessage.success('已复制');
  } catch {
    ElMessage.warning('复制失败，请手动选择文本复制');
  }
}
onMounted(load);
</script>

<style scoped>
.settings { display: grid; gap: 16px; max-width: 880px; }
.settings-card { display: grid; gap: 12px; padding: 16px 20px; border: 1px solid var(--el-border-color-lighter); border-radius: 12px; background: var(--el-bg-color); }
.settings-card header { display: flex; align-items: center; gap: 10px; }
.settings-card h3 { margin: 0; color: var(--el-text-color-primary); font-size: 15px; font-weight: 600; }
.settings-desc { margin: 0; color: var(--el-text-color-secondary); font-size: 13px; line-height: 1.7; }
.settings-desc code, .copy-line code { padding: 1px 6px; border-radius: 4px; background: var(--el-fill-color); font-size: 12px; }
.settings-card :deep(.el-button i) { margin-right: 4px; }
.key-box { display: grid; gap: 6px; }
.key-box label { color: var(--el-text-color-regular); font-size: 13px; }
.key-box small { color: var(--el-text-color-secondary); font-size: 12px; }
.copy-line { display: flex; align-items: center; gap: 8px; min-width: 0; }
.copy-line code { overflow-wrap: anywhere; }
.env-block { display: grid; gap: 8px; justify-items: start; }
.pay-form { display: grid; gap: 14px; }
.pay-channel { display: grid; gap: 10px; padding: 14px 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; }
.pay-channel__head { display: flex; align-items: center; justify-content: space-between; }
.pay-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 14px; }
.pay-grid :deep(.el-form-item) { margin-bottom: 10px; }
.pay-wide { grid-column: 1 / -1; }
.notify { padding: 2px 8px; border-radius: 4px; background: var(--el-fill-color); font-size: 12px; overflow-wrap: anywhere; }
@media (max-width: 640px) { .pay-grid { grid-template-columns: 1fr; } }
.env-block pre { width: 100%; margin: 0; box-sizing: border-box; overflow: auto; padding: 12px 14px; border-radius: 10px; background: #0f172a; color: #e2e8f0; font-size: 12px; line-height: 1.7; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
