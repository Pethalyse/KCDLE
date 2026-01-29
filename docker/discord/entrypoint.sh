#!/usr/bin/env sh
set -e

cd /app

if [ "${DISCORD_SKIP_COMMAND_REGISTRATION:-0}" != "1" ]; then
  node dist/registerCommands.js
fi

exec node dist/index.js
