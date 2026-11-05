<template>
  <svg viewBox="0 0 480 360" xmlns="http://www.w3.org/2000/svg" fill="none">
    <defs>
      <linearGradient id="g500a" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="var(--el-color-primary)" stop-opacity="0.9" />
        <stop offset="100%" stop-color="var(--el-color-primary-light-3)" stop-opacity="0.9" />
      </linearGradient>
      <linearGradient id="g500b" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="var(--el-color-primary-light-7)" />
        <stop offset="100%" stop-color="var(--el-color-primary-light-9)" />
      </linearGradient>
    </defs>
    <ellipse cx="240" cy="320" rx="180" ry="14" fill="var(--el-color-primary)" opacity="0.12" />
    <g transform="translate(120, 70)">
      <rect x="0" y="50" width="240" height="160" rx="14" fill="url(#g500b)" />
      <rect x="0" y="50" width="240" height="32" rx="14" fill="url(#g500a)" />
      <circle cx="20" cy="66" r="5" fill="#fff" opacity="0.85" />
      <circle cx="38" cy="66" r="5" fill="#fff" opacity="0.65" />
      <circle cx="56" cy="66" r="5" fill="#fff" opacity="0.45" />
      <g font-family="ui-monospace, monospace" font-size="14" fill="var(--el-color-primary)">
        <text x="20" y="110">$ run server</text>
        <text x="20" y="135" opacity="0.7">> connecting...</text>
        <text x="20" y="160" fill="#f56c6c" font-weight="700">✘ Internal Server Error</text>
        <text x="20" y="185" opacity="0.7">> code: 500</text>
      </g>
    </g>
    <circle cx="80" cy="90" r="8" fill="var(--el-color-primary)" opacity="0.4" />
    <circle cx="420" cy="120" r="6" fill="var(--el-color-primary)" opacity="0.5" />
    <circle cx="400" cy="260" r="10" fill="var(--el-color-primary)" opacity="0.3" />
    <circle cx="60" cy="260" r="5" fill="var(--el-color-primary)" opacity="0.4" />
  </svg>
</template>
