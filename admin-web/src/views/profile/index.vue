<template>
  <PageWrapper title="个人中心" subtitle="管理你的基本信息、安全设置、密码与消息">
    <el-row :gutter="16">
      <!-- 左：账号概览卡片 -->
      <el-col :xs="24" :md="8">
        <el-card shadow="never" class="profile-card">
          <div class="profile-card__head">
            <el-avatar :size="92" :src="basicForm.avatar" class="profile-card__avatar">
              {{ (basicForm.nickname || userStore.nickname).charAt(0).toUpperCase() }}
            </el-avatar>
            <h3 class="profile-card__name">{{ basicForm.nickname || userStore.nickname || '未命名' }}</h3>
            <p class="profile-card__email">{{ basicForm.email || '-' }}</p>
            <div class="profile-card__roles">
              <el-tag v-for="r in userStore.roles" :key="r" size="small" effect="light" round>{{ r }}</el-tag>
            </div>
          </div>

          <el-divider />

          <ul class="profile-card__list">
            <li>
              <i class="i-ep-user" />
              <span>用户名</span>
              <em>{{ userStore.userInfo?.username }}</em>
            </li>
            <li>
              <i class="i-ep-phone" />
              <span>手机</span>
              <em>{{ basicForm.mobile || '-' }}</em>
            </li>
            <li>
              <i class="i-ep-message" />
              <span>邮箱</span>
              <em>{{ basicForm.email || '-' }}</em>
            </li>
            <li>
              <i class="i-ep-clock" />
              <span>最近登录</span>
              <em>{{ lastLogin }}</em>
            </li>
          </ul>
        </el-card>
      </el-col>

      <!-- 右：Tab 区 -->
      <el-col :xs="24" :md="16">
        <el-card shadow="never">
          <el-tabs v-model="activeTab" class="profile-tabs">
            <!-- ============ 1. 基本资料 ============ -->
            <el-tab-pane name="basic">
              <template #label><i class="i-ep-user mr-1" /> 基本资料</template>
              <el-form
                ref="basicFormRef"
                :model="basicForm"
                :rules="basicRules"
                label-width="92px"
                class="max-w-2xl"
              >
                <el-form-item label="头像">
                  <Upload
                    v-model="basicForm.avatar"
                    type="image"
                    biz-type="avatar"
                    :max-size="2"
                    accept="image/*"
                    hint="支持 jpg/png，建议尺寸 200x200"
                  />
                </el-form-item>
                <el-form-item label="昵称" prop="nickname">
                  <el-input v-model="basicForm.nickname" maxlength="20" show-word-limit />
                </el-form-item>
                <el-form-item label="邮箱" prop="email">
                  <el-input v-model="basicForm.email" />
                </el-form-item>
                <el-form-item label="手机" prop="mobile">
                  <el-input v-model="basicForm.mobile" maxlength="11" />
                </el-form-item>
                <el-form-item label="个人简介">
                  <el-input
                    v-model="basicForm.bio"
                    type="textarea"
                    :rows="3"
                    maxlength="120"
                    show-word-limit
                  />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="basicLoading" @click="onSaveBasic">保存修改</el-button>
                  <el-button @click="onResetBasic">重置</el-button>
                </el-form-item>
              </el-form>
            </el-tab-pane>

            <!-- ============ 2. 安全设置 ============ -->
            <el-tab-pane name="security">
              <template #label><i class="i-ep-lock mr-1" /> 安全设置</template>
              <ul class="profile-security">
                <li>
                  <div class="profile-security__info">
                    <i class="i-ep-iphone profile-security__icon" />
                    <div>
                      <h4>密保手机</h4>
                      <p>已绑定 {{ maskMobile(basicForm.mobile) }}</p>
                    </div>
                  </div>
                  <el-button type="primary" link @click="activeTab = 'basic'">前往修改</el-button>
                </li>
                <li>
                  <div class="profile-security__info">
                    <i class="i-ep-message profile-security__icon" />
                    <div>
                      <h4>密保邮箱</h4>
                      <p>已绑定 {{ basicForm.email || '未绑定' }}</p>
                    </div>
                  </div>
                  <el-button type="primary" link @click="activeTab = 'basic'">前往修改</el-button>
                </li>
                <li>
                  <div class="profile-security__info">
                    <i class="i-ep-key profile-security__icon" />
                    <div>
                      <h4>账户密码</h4>
                      <p>建议每 90 天更换一次，长度不少于 6 位</p>
                    </div>
                  </div>
                  <el-button type="primary" link @click="activeTab = 'password'">立即修改</el-button>
                </li>
                <li>
                  <div class="profile-security__info">
                    <i class="i-ep-monitor profile-security__icon" />
                    <div>
                      <h4>登录设备</h4>
                      <p>当前共 1 台设备登录（演示数据）</p>
                    </div>
                  </div>
                  <el-button type="danger" link @click="onLogoutAll">退出全部</el-button>
                </li>
                <li>
                  <div class="profile-security__info">
                    <i class="i-ep-link profile-security__icon" />
                    <div>
                      <h4>账号绑定</h4>
                      <p>已绑定 {{ boundCount }} / {{ bindings.length }} 个第三方账号</p>
                    </div>
                  </div>
                  <el-button type="primary" link @click="bindingDrawer = true">管理绑定</el-button>
                </li>
              </ul>
            </el-tab-pane>

            <!-- ============ 3. 修改密码 ============ -->
            <el-tab-pane name="password">
              <template #label><i class="i-ep-key mr-1" /> 修改密码</template>
              <el-form
                ref="pwdFormRef"
                :model="pwdForm"
                :rules="pwdRules"
                label-width="92px"
                class="max-w-md"
              >
                <el-form-item label="原密码" prop="oldPassword">
                  <el-input v-model="pwdForm.oldPassword" type="password" show-password />
                </el-form-item>
                <el-form-item label="新密码" prop="newPassword">
                  <el-input v-model="pwdForm.newPassword" type="password" show-password />
                  <div class="pwd-strength">
                    <span
                      v-for="(seg, i) in 3"
                      :key="i"
                      :class="['pwd-strength__seg', { active: pwdLevel > i }]"
                    />
                    <span class="pwd-strength__label">{{ pwdLevelLabel }}</span>
                  </div>
                </el-form-item>
                <el-form-item label="确认密码" prop="confirmPassword">
                  <el-input v-model="pwdForm.confirmPassword" type="password" show-password />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="pwdLoading" @click="onChangePwd">确认修改</el-button>
                  <el-button @click="onResetPwd">重置</el-button>
                </el-form-item>
              </el-form>
            </el-tab-pane>

            <!-- ============ 4. 消息中心 ============ -->
            <el-tab-pane name="message">
              <template #label>
                <i class="i-ep-bell mr-1" />
                消息中心
                <el-badge v-if="unreadTotal" :value="unreadTotal" :max="99" class="ml-1" />
              </template>

              <div class="profile-msg__bar">
                <el-radio-group v-model="msgFilter" size="small">
                  <el-radio-button value="all">全部 ({{ messages.length }})</el-radio-button>
                  <el-radio-button value="notice">通知</el-radio-button>
                  <el-radio-button value="message">消息</el-radio-button>
                  <el-radio-button value="todo">待办</el-radio-button>
                </el-radio-group>
                <div class="flex-1" />
                <el-button size="small" :disabled="!unreadTotal" @click="onReadAll">
                  <i class="i-ep-check mr-1" /> 全部已读
                </el-button>
                <el-button size="small" type="danger" plain :disabled="!messages.length" @click="onClearMsg">
                  <i class="i-ep-delete mr-1" /> 清空
                </el-button>
              </div>

              <ul class="profile-msg" v-loading="msgLoading">
                <li
                  v-for="m in filteredMessages"
                  :key="m.id"
                  :class="{ unread: !m.read }"
                  @click="onReadMsg(m)"
                >
                  <span class="profile-msg__icon" :style="{ background: m.color }">
                    <i :class="m.icon" />
                  </span>
                  <div class="profile-msg__body">
                    <div class="profile-msg__title">
                      {{ m.title }}
                      <el-tag size="small" :type="MSG_TAG[m.type]" effect="light" round>
                        {{ MSG_LABEL[m.type] }}
                      </el-tag>
                    </div>
                    <div class="profile-msg__desc">{{ m.desc }}</div>
                    <div class="profile-msg__time">{{ m.time }}</div>
                  </div>
                  <span v-if="!m.read" class="profile-msg__dot" />
                </li>
                <li v-if="!filteredMessages.length" class="profile-msg__empty">暂无消息</li>
              </ul>
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </el-col>
    </el-row>

    <!-- 账号绑定抽屉 -->
    <el-drawer v-model="bindingDrawer" title="账号绑定" size="420px">
      <ul class="profile-binding" v-loading="bindingLoading">
        <li v-for="b in bindings" :key="b.type">
          <div class="profile-binding__info">
            <span class="profile-binding__icon" :style="{ background: bindingMeta[b.type].color }">
              <i :class="bindingMeta[b.type].icon" />
            </span>
            <div>
              <h4>{{ bindingMeta[b.type].label }}</h4>
              <p v-if="b.bound">{{ b.account }} · 绑定于 {{ b.boundAt }}</p>
              <p v-else>未绑定，绑定后可使用快捷登录</p>
            </div>
          </div>
          <el-button :type="b.bound ? 'danger' : 'primary'" link @click="onToggleBind(b)">
            {{ b.bound ? '解除绑定' : '立即绑定' }}
          </el-button>
        </li>
      </ul>
    </el-drawer>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus';
import { useUserStore } from '@/store/modules/user';
import { profileApi, type BindingItem } from '@/api/profile';
import { notificationApi, type NoticeItem, type NoticeType } from '@/api/notification';
import PageWrapper from '@/components/PageWrapper/index.vue';
import Upload from '@/components/Upload/index.vue';

defineOptions({ name: 'Profile' });

const userStore = useUserStore();
const activeTab = ref<'basic' | 'security' | 'password' | 'message'>('basic');

/* ============ 基本资料 ============ */
const basicFormRef = ref<FormInstance>();
const basicLoading = ref(false);
const basicForm = reactive({
  nickname: userStore.userInfo?.nickname || '',
  email: userStore.userInfo?.email || '',
  mobile: userStore.userInfo?.mobile || '',
  avatar: userStore.userInfo?.avatar || '',
  bio: '热爱代码，热爱生活。'
});
const basicRules: FormRules = {
  nickname: [{ required: true, message: '请输入昵称', trigger: 'blur' }],
  email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
  mobile: [{ pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号', trigger: 'blur' }]
};

async function onSaveBasic() {
  if (!basicFormRef.value) return;
  await basicFormRef.value.validate();
  basicLoading.value = true;
  try {
    const info = await profileApi.update({
      nickname: basicForm.nickname,
      email: basicForm.email,
      mobile: basicForm.mobile,
      avatar: basicForm.avatar
    });
    userStore.updateUserInfo(info);
    ElMessage.success('资料已更新');
  } finally {
    basicLoading.value = false;
  }
}

function onResetBasic() {
  basicForm.nickname = userStore.userInfo?.nickname || '';
  basicForm.email = userStore.userInfo?.email || '';
  basicForm.mobile = userStore.userInfo?.mobile || '';
  basicForm.avatar = userStore.userInfo?.avatar || '';
  basicForm.bio = '热爱代码，热爱生活。';
}

/* ============ 修改密码（独立 tab） ============ */
const pwdFormRef = ref<FormInstance>();
const pwdLoading = ref(false);
const pwdForm = reactive({
  oldPassword: '',
  newPassword: '',
  confirmPassword: ''
});
const pwdRules: FormRules = {
  oldPassword: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
  newPassword: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '至少 6 位', trigger: 'blur' }
  ],
  confirmPassword: [
    { required: true, message: '请再次输入新密码', trigger: 'blur' },
    {
      validator: (_r, val, cb) => {
        if (val !== pwdForm.newPassword) cb(new Error('两次密码不一致'));
        else cb();
      },
      trigger: 'blur'
    }
  ]
};

const pwdLevel = computed(() => {
  const v = pwdForm.newPassword;
  if (!v || v.length < 6) return 0;
  let score = 0;
  if (v.length >= 8) score++;
  if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
  if (/\d/.test(v) && /[^\w\s]/.test(v)) score++;
  return Math.min(score, 3);
});
const pwdLevelLabel = computed(() => ['请输入', '弱', '中', '强'][pwdLevel.value]);

async function onChangePwd() {
  if (!pwdFormRef.value) return;
  await pwdFormRef.value.validate();
  pwdLoading.value = true;
  try {
    await profileApi.changePassword({
      oldPassword: pwdForm.oldPassword,
      newPassword: pwdForm.newPassword
    });
    onResetPwd();
    ElMessage.success('密码已更新，下次登录请使用新密码');
  } finally {
    pwdLoading.value = false;
  }
}

function onResetPwd() {
  pwdForm.oldPassword = '';
  pwdForm.newPassword = '';
  pwdForm.confirmPassword = '';
}

function maskMobile(m?: string) {
  if (!m) return '未绑定';
  return m.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2');
}

function onLogoutAll() {
  ElMessageBox.confirm('确认退出当前账号在所有设备上的登录？', '提示', { type: 'warning' }).then(() => {
    ElMessage.success('已发送退出指令（演示）');
  });
}

/* ============ 账号绑定（移到抽屉） ============ */
const bindingDrawer = ref(false);
const bindingLoading = ref(false);
const bindings = ref<BindingItem[]>([]);
const bindingMeta: Record<BindingItem['type'], { label: string; icon: string; color: string }> = {
  wechat: { label: '微信', icon: 'i-ep-chat-dot-round', color: '#22c55e' },
  qq: { label: 'QQ', icon: 'i-ep-message', color: '#3b82f6' },
  github: { label: 'GitHub', icon: 'i-ep-link', color: '#1f2937' },
  dingtalk: { label: '钉钉', icon: 'i-ep-bell', color: '#0ea5e9' }
};
const boundCount = computed(() => bindings.value.filter((b) => b.bound).length);

async function loadBindings() {
  bindingLoading.value = true;
  try {
    bindings.value = await profileApi.bindings();
  } finally {
    bindingLoading.value = false;
  }
}

async function onToggleBind(item: BindingItem) {
  bindingLoading.value = true;
  try {
    await profileApi.toggleBinding(item.type, !item.bound);
    await loadBindings();
  } finally {
    bindingLoading.value = false;
  }
}

/* ============ 消息中心 ============ */
const MSG_LABEL: Record<NoticeType, string> = {
  notice: '通知',
  message: '消息',
  todo: '待办'
};
const MSG_TAG: Record<NoticeType, 'primary' | 'success' | 'warning'> = {
  notice: 'primary',
  message: 'success',
  todo: 'warning'
};

const msgLoading = ref(false);
const messages = ref<NoticeItem[]>([]);
const msgFilter = ref<'all' | NoticeType>('all');
const filteredMessages = computed(() =>
  msgFilter.value === 'all'
    ? messages.value
    : messages.value.filter((m) => m.type === msgFilter.value)
);
const unreadTotal = computed(() => messages.value.filter((m) => !m.read).length);

async function loadMessages() {
  msgLoading.value = true;
  try {
    messages.value = await notificationApi.list();
  } finally {
    msgLoading.value = false;
  }
}

async function onReadMsg(m: NoticeItem) {
  if (m.read) return;
  await notificationApi.read(m.id);
  m.read = true;
}

async function onReadAll() {
  await notificationApi.readAll();
  messages.value.forEach((m) => (m.read = true));
  ElMessage.success('已全部标记为已读');
}

async function onClearMsg() {
  await ElMessageBox.confirm('确认清空所有消息？', '提示', { type: 'warning' });
  await notificationApi.clear();
  messages.value = [];
  ElMessage.success('已清空');
}

const lastLogin = '2025-04-21 09:12:38';

onMounted(() => {
  loadBindings();
  loadMessages();
});
</script>

<style scoped>
.profile-card__head {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.profile-card__avatar {
  background: var(--el-color-primary);
  color: #fff;
  font-size: 28px;
  font-weight: 600;
}
.profile-card__name {
  margin: 14px 0 4px;
  font-size: 18px;
  font-weight: 600;
  color: var(--app-text);
}
.profile-card__email {
  margin: 0 0 12px;
  font-size: 13px;
  color: var(--app-text-secondary);
}
.profile-card__roles {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
}
.profile-card__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.profile-card__list li {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: var(--app-text);
}
.profile-card__list li i {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--el-color-primary) 8%, transparent);
  color: var(--el-color-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.profile-card__list li span {
  flex: 1;
  color: var(--app-text-secondary);
}
.profile-card__list li em {
  font-style: normal;
  color: var(--app-text);
  font-weight: 500;
}

.profile-tabs {
  --el-tabs-header-height: 44px;
}

.profile-security,
.profile-binding {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
}
.profile-security li,
.profile-binding li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 4px;
  border-bottom: 1px dashed var(--app-border);
}
.profile-security li:last-child,
.profile-binding li:last-child {
  border-bottom: none;
}
.profile-security__info,
.profile-binding__info {
  display: flex;
  align-items: center;
  gap: 14px;
}
.profile-security__icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--el-color-primary) 8%, transparent);
  color: var(--el-color-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}
.profile-security h4,
.profile-binding h4 {
  margin: 0 0 4px;
  font-size: 14px;
  font-weight: 600;
  color: var(--app-text);
}
.profile-security p,
.profile-binding p {
  margin: 0;
  font-size: 12px;
  color: var(--app-text-secondary);
}

.profile-binding__icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 20px;
}

/* ============ 密码强度 ============ */
.pwd-strength {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 6px;
}
.pwd-strength__seg {
  flex: 0 0 50px;
  height: 4px;
  border-radius: 2px;
  background: var(--app-border);
  transition: background 0.2s;
}
.pwd-strength__seg.active:nth-child(1) {
  background: #f56c6c;
}
.pwd-strength__seg.active:nth-child(2) {
  background: #e6a23c;
}
.pwd-strength__seg.active:nth-child(3) {
  background: #67c23a;
}
.pwd-strength__label {
  margin-left: 8px;
  font-size: 12px;
  color: var(--app-text-secondary);
}

/* ============ 消息中心 ============ */
.profile-msg__bar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}
.flex-1 {
  flex: 1;
}
.profile-msg {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
}
.profile-msg li {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 8px;
  border-bottom: 1px dashed var(--app-border);
  cursor: pointer;
  transition: background 0.15s;
}
.profile-msg li:hover {
  background: color-mix(in srgb, var(--el-color-primary) 4%, transparent);
}
.profile-msg li:last-child {
  border-bottom: none;
}
.profile-msg__icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 16px;
  flex-shrink: 0;
}
.profile-msg__body {
  flex: 1;
  min-width: 0;
}
.profile-msg__title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 500;
  color: var(--app-text);
}
.profile-msg__desc {
  margin-top: 4px;
  font-size: 13px;
  color: var(--app-text-secondary);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.profile-msg__time {
  margin-top: 4px;
  font-size: 12px;
  color: var(--el-text-color-placeholder);
}
.profile-msg__dot {
  position: absolute;
  top: 18px;
  right: 8px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--el-color-danger);
}
.profile-msg__empty {
  text-align: center;
  padding: 40px 0;
  color: var(--app-text-secondary);
  cursor: default;
}
.profile-msg__empty:hover {
  background: transparent;
}
</style>
