#!/bin/bash

echo "========================================"
echo "FunAdmin MCP 服务器启动脚本"
echo "========================================"

# 设置环境变量
export MCP_TRANSPORT=sse
export MCP_HOST=127.0.0.1
export MCP_PORT=8080
export MCP_PATH=mcp
export MCP_TIMEOUT=60000
export MCP_MEMORY_LIMIT=256M
export MCP_MAX_EXECUTION_TIME=0

echo "传输协议: $MCP_TRANSPORT"
echo "监听地址: $MCP_HOST:$MCP_PORT"
echo "超时设置: ${MCP_TIMEOUT}ms"
echo "内存限制: $MCP_MEMORY_LIMIT"
echo "执行时间限制: ${MCP_MAX_EXECUTION_TIME}s"

echo ""
echo "正在启动 MCP 服务器..."
php mcp-server.php
