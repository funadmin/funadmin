/**
 * 全局水印
 * - 通过 canvas 生成倾斜文字图案的 dataURL，作为 fixed 浮层背景平铺
 * - MutationObserver 防止水印节点被恶意移除
 */
const WATERMARK_ID = 'app-global-watermark';
let mutationObserver: MutationObserver | null = null;

interface WatermarkOptions {
  text: string;
  color?: string;
  fontSize?: number;
  width?: number;
  height?: number;
  rotate?: number;
}

function createWatermarkDataURL(opts: WatermarkOptions): string {
  const {
    text,
    color = 'rgba(0, 0, 0, 0.08)',
    fontSize = 22,
    width = 320,
    height = 220,
    rotate = -22
  } = opts;

  // 适配高分屏，避免文字发虚；同时 css 上仍按逻辑像素平铺
  const dpr = Math.max(window.devicePixelRatio || 1, 1);
  const canvas = document.createElement('canvas');
  canvas.width = width * dpr;
  canvas.height = height * dpr;
  const ctx = canvas.getContext('2d');
  if (!ctx) return '';

  ctx.scale(dpr, dpr);
  ctx.clearRect(0, 0, width, height);
  ctx.font = `600 ${fontSize}px -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif`;
  ctx.fillStyle = color;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.translate(width / 2, height / 2);
  ctx.rotate((rotate * Math.PI) / 180);
  ctx.fillText(text, 0, 0);
  return canvas.toDataURL('image/png');
}

function ensureWatermarkNode(): HTMLDivElement {
  let node = document.getElementById(WATERMARK_ID) as HTMLDivElement | null;
  if (!node) {
    node = document.createElement('div');
    node.id = WATERMARK_ID;
    node.style.cssText = [
      'position:fixed',
      'left:0',
      'top:0',
      'right:0',
      'bottom:0',
      'pointer-events:none',
      'background-repeat:repeat',
      // 必须低于 Element Plus 弹窗（默认 2000+）/ Drawer / MessageBox / Dropdown，
      // 否则水印会盖在确认弹窗、按钮、遮罩之上，造成对比度异常
      'z-index:1',
      'user-select:none'
    ].join(';');
    document.body.appendChild(node);
  }
  return node;
}

/** 启用全局水印 */
export function setWatermark(text: string, isDark = false) {
  if (!text) {
    removeWatermark();
    return;
  }
  const node = ensureWatermarkNode();
  // 夜间模式自动反白，避免黑底黑字看不见
  const color = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.1)';
  const tileWidth = 320;
  const tileHeight = 220;
  const url = createWatermarkDataURL({ text, color, width: tileWidth, height: tileHeight });
  node.style.backgroundImage = `url('${url}')`;
  // 配合 DPR 放大的画布，按逻辑像素平铺，避免在不同屏幕上尺寸漂移
  node.style.backgroundSize = `${tileWidth}px ${tileHeight}px`;

  // 防止水印 DOM 被移除：监听 body 子节点变化，被移除则立即重建
  if (!mutationObserver) {
    mutationObserver = new MutationObserver(() => {
      const exists = document.getElementById(WATERMARK_ID);
      if (!exists && node.dataset.active === '1') {
        document.body.appendChild(node);
      }
    });
    mutationObserver.observe(document.body, { childList: true });
  }
  node.dataset.active = '1';
}

/** 移除全局水印 */
export function removeWatermark() {
  const node = document.getElementById(WATERMARK_ID);
  if (node) {
    node.dataset.active = '0';
    node.remove();
  }
  mutationObserver?.disconnect();
  mutationObserver = null;
}
