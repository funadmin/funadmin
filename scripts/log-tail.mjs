// 跟随 webman 当天的应用日志（Monolog RotatingFileHandler：<dir>/webman-YYYY-MM-DD.log）。
//
// - 从文件当前末尾开始，只输出新追加的完整行（半行等到换行再输出）。
// - 文件尚不存在时持续等待，创建后从头读取。
// - 文件被截断或替换（inode 变化）时从头读取。
// - 本地日期变化时切换到新一天的文件，旧文件不再跟随。
// 采用轮询 stat + 读取增量的方式，避免 fs.watch 在文件不存在 / 被替换时的平台差异。
import { open, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { StringDecoder } from 'node:string_decoder';

const pad = n => String(n).padStart(2, '0');

export function dailyLogName(date) {
  return `webman-${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}.log`;
}

async function statOrNull(file) {
  try {
    return await stat(file);
  } catch {
    return null;
  }
}

export function tailDailyLog({ dir, onLine, now = () => new Date(), interval = 500 }) {
  let file = null;
  let position = 0;
  let ino = null;
  let buffer = '';
  let decoder = new StringDecoder('utf8');
  let busy = false;
  let stopped = false;

  const emit = text => {
    buffer += text;
    const lines = buffer.split(/\r?\n/);
    buffer = lines.pop();
    for (const line of lines) if (line.trim() !== '') onLine(line);
  };
  const reset = () => {
    position = 0;
    buffer = '';
    decoder = new StringDecoder('utf8');
  };

  async function readNew(size) {
    const handle = await open(file, 'r');
    try {
      const chunk = Buffer.alloc(64 * 1024);
      while (position < size && !stopped) {
        const { bytesRead } = await handle.read(chunk, 0, Math.min(chunk.length, size - position), position);
        if (bytesRead === 0) break;
        position += bytesRead;
        emit(decoder.write(chunk.subarray(0, bytesRead)));
      }
    } finally {
      await handle.close();
    }
  }

  async function poll(initial = false) {
    if (busy || stopped) return;
    busy = true;
    try {
      const target = join(dir, dailyLogName(now()));
      if (target !== file) {
        // 换了一天：把旧文件残留的半行输出，新文件从头读（启动时则从末尾开始）。
        if (buffer.trim() !== '') onLine(buffer);
        file = target;
        ino = null;
        reset();
      }
      const info = await statOrNull(file);
      if (!info) {
        ino = null;
        reset();
        return;
      }
      if (initial) {
        position = info.size;
        ino = info.ino;
        return;
      }
      if ((ino !== null && info.ino !== ino) || info.size < position) reset();
      ino = info.ino;
      if (info.size > position) await readNew(info.size);
    } catch {
      // 读取失败（例如文件刚被删除）时等待下一轮。
    } finally {
      busy = false;
    }
  }

  const ready = poll(true);
  const timer = setInterval(() => { poll(); }, interval);
  timer.unref?.();

  return {
    ready,
    poll: () => poll(),
    stop() {
      stopped = true;
      clearInterval(timer);
    },
  };
}
