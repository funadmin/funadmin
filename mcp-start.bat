@echo off
echo ========================================
echo FunAdmin MCP 服务器启动脚本
echo ========================================

REM 设置环境变量
set MCP_TRANSPORT=sse
set MCP_HOST=127.0.0.1
set MCP_PORT=8080
set MCP_PATH=mcp
set MCP_TIMEOUT=60000
set MCP_MEMORY_LIMIT=256M
set MCP_MAX_EXECUTION_TIME=0

echo 传输协议: %MCP_TRANSPORT%
echo 监听地址: %MCP_HOST%:%MCP_PORT%
echo 超时设置: %MCP_TIMEOUT%ms
echo 内存限制: %MCP_MEMORY_LIMIT%
echo 执行时间限制: %MCP_MAX_EXECUTION_TIME%s

echo.
echo 正在启动 MCP 服务器...
php mcp-server.php

pause
