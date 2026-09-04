<template>
  <PageWrapper title="个人中心" subtitle="管理当前账号的基本资料与登录密码">
    <el-row :gutter="16">
      <el-col :xs="24" :md="8">
        <el-card shadow="never" class="profile-card">
          <div class="profile-card__head">
            <el-avatar :size="92" :src="basicForm.avatar" class="profile-card__avatar">
              {{ (basicForm.nickname || userStore.nickname).charAt(0).toUpperCase() }}
            </el-avatar>
            <h3 class="profile-card__name">{{ basicForm.nickname || userStore.nickname || '未命名' }}</h3>
            <p class="profile-card__email">{{ basicForm.email || '-' }}</p>
          </div>
          <el-divider />
          <ul class="profile-card__list">
            <li><i class="i-ep-user" /><span>用户名</span><em>{{ profile?.username || '-' }}</em></li>
            <li><i class="i-ep-phone" /><span>手机</span><em>{{ basicForm.mobile || '-' }}</em></li>
            <li><i class="i-ep-message" /><span>邮箱</span><em>{{ basicForm.email || '-' }}</em></li>
            <li><i class="i-ep-location" /><span>最近登录 IP</span><em>{{ profile?.lastLoginIp || '-' }}</em></li>
          </ul>
        </el-card>
      </el-col>

      <el-col :xs="24" :md="16">
        <el-card shadow="never">
          <el-tabs v-model="activeTab">
            <el-tab-pane name="basic">
              <template #label><i class="i-ep-user mr-1" /> 基本资料</template>
              <el-form
                ref="basicFormRef"
                :model="basicForm"
                :rules="basicRules"
                label-width="92px"
                class="max-w-2xl"
                v-loading="profileLoading"
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
                  <el-input v-model="basicForm.nickname" maxlength="50" show-word-limit />
                </el-form-item>
                <el-form-item label="邮箱" prop="email">
                  <el-input v-model="basicForm.email" />
                </el-form-item>
                <el-form-item label="手机" prop="mobile">
                  <el-input v-model="basicForm.mobile" maxlength="11" />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="basicLoading" @click="onSaveBasic">保存修改</el-button>
                  <el-button @click="resetBasic">重置</el-button>
                </el-form-item>
              </el-form>
            </el-tab-pane>

            <el-tab-pane name="password">
              <template #label><i class="i-ep-lock mr-1" /> 修改密码</template>
              <el-alert
                title="修改成功后当前会话会立即失效，需要使用新密码重新登录"
                type="warning"
                :closable="false"
                show-icon
                class="mb-5"
              />
              <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-width="92px" class="max-w-lg">
                <el-form-item label="原密码" prop="oldPassword">
                  <el-input v-model="pwdForm.oldPassword" type="password" show-password autocomplete="current-password" />
                </el-form-item>
                <el-form-item label="新密码" prop="newPassword">
                  <el-input v-model="pwdForm.newPassword" type="password" show-password autocomplete="new-password" />
                </el-form-item>
                <el-form-item label="确认密码" prop="confirmPassword">
                  <el-input v-model="pwdForm.confirmPassword" type="password" show-password autocomplete="new-password" />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="pwdLoading" @click="onChangePwd">确认修改</el-button>
                  <el-button @click="resetPassword">重置</el-button>
                </el-form-item>
              </el-form>
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </el-col>
    </el-row>
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, type FormInstance, type FormRules } from 'element-plus';
import { profileApi, type ProfileInfo } from '@/api/profile';
import { useUserStore } from '@/store/modules/user';
import PageWrapper from '@/components/PageWrapper/index.vue';
import Upload from '@/components/Upload/index.vue';

defineOptions({ name: 'Profile' });

const router = useRouter();
const userStore = useUserStore();
const activeTab = ref<'basic' | 'password'>('basic');
const profile = ref<ProfileInfo | null>(null);
const profileLoading = ref(false);
const basicLoading = ref(false);
const pwdLoading = ref(false);
const basicFormRef = ref<FormInstance>();
const pwdFormRef = ref<FormInstance>();

const basicForm = reactive({ nickname: '', email: '', mobile: '', avatar: '' });
const pwdForm = reactive({ oldPassword: '', newPassword: '', confirmPassword: '' });

const basicRules: FormRules = {
  nickname: [{ required: true, message: '请输入昵称', trigger: 'blur' }],
  email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
  mobile: [{ pattern: /^1[3-9]\d{9}$/, message: '手机号格式不正确', trigger: 'blur' }]
};
const pwdRules: FormRules = {
  oldPassword: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
  newPassword: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 8, message: '新密码至少 8 位', trigger: 'blur' }
  ],
  confirmPassword: [
    { required: true, message: '请再次输入新密码', trigger: 'blur' },
    {
      validator: (_rule, value, callback) => {
        value === pwdForm.newPassword ? callback() : callback(new Error('两次密码不一致'));
      },
      trigger: 'blur'
    }
  ]
};

function applyProfile(info: ProfileInfo) {
  profile.value = info;
  basicForm.nickname = info.nickname;
  basicForm.email = info.email || '';
  basicForm.mobile = info.mobile || '';
  basicForm.avatar = info.avatar || '';
}

async function loadProfile() {
  profileLoading.value = true;
  try {
    applyProfile(await profileApi.detail());
  } finally {
    profileLoading.value = false;
  }
}

async function onSaveBasic() {
  await basicFormRef.value?.validate();
  basicLoading.value = true;
  try {
    const info = await profileApi.update({ ...basicForm });
    applyProfile(info);
    userStore.updateUserInfo(info);
    ElMessage.success('资料已更新');
  } finally {
    basicLoading.value = false;
  }
}

function resetBasic() {
  if (profile.value) applyProfile(profile.value);
}

async function onChangePwd() {
  await pwdFormRef.value?.validate();
  pwdLoading.value = true;
  try {
    await profileApi.changePassword({ oldPassword: pwdForm.oldPassword, newPassword: pwdForm.newPassword });
    userStore.resetState();
    ElMessage.success('密码已更新，请重新登录');
    await router.replace('/login');
  } finally {
    pwdLoading.value = false;
  }
}

function resetPassword() {
  pwdFormRef.value?.resetFields();
  Object.assign(pwdForm, { oldPassword: '', newPassword: '', confirmPassword: '' });
}

onMounted(loadProfile);
</script>

<style scoped>
.profile-card__head { display: flex; flex-direction: column; align-items: center; text-align: center; }
.profile-card__avatar { background: var(--el-color-primary); color: #fff; font-size: 28px; font-weight: 600; }
.profile-card__name { margin: 14px 0 4px; font-size: 18px; font-weight: 600; color: var(--app-text); }
.profile-card__email { margin: 0; font-size: 13px; color: var(--app-text-secondary); }
.profile-card__list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 12px; }
.profile-card__list li { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--app-text); }
.profile-card__list li i { width: 28px; height: 28px; border-radius: 8px; background: color-mix(in srgb, var(--el-color-primary) 8%, transparent); color: var(--el-color-primary); display: inline-flex; align-items: center; justify-content: center; }
.profile-card__list li span { flex: 1; color: var(--app-text-secondary); }
.profile-card__list li em { font-style: normal; color: var(--app-text); font-weight: 500; }
</style>
