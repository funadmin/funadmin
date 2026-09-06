export interface PurgeConfirmation {
  purgeConfirm: string;
}

export async function confirmAction(
  confirm: () => Promise<unknown>,
  action: () => Promise<unknown>
): Promise<boolean> {
  try {
    await confirm();
  } catch (reason) {
    if (reason === 'cancel' || reason === 'close') {
      return false;
    }
    throw reason;
  }
  await action();
  return true;
}

export function buildPurgeConfirmation(pluginCode: string, confirmation: string): PurgeConfirmation {
  if (confirmation !== pluginCode) {
    throw new Error(`彻底清理数据时必须输入插件标识 ${pluginCode}`);
  }
  return { purgeConfirm: confirmation };
}
