export interface PurgeConfirmation {
  purge: boolean;
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

export function buildPurgeConfirmation(pluginName: string, purge: boolean, confirmation: string): PurgeConfirmation {
  if (purge && confirmation !== pluginName) {
    throw new Error(`彻底清理数据时必须输入插件名称 ${pluginName}`);
  }
  return { purge, purgeConfirm: purge ? confirmation : '' };
}
