# Public website, API, and realtime

The local proxy in `src/devProxy.js` lets one ngrok domain serve the new
CompliTrack website, Laravel API, and Socket.IO service.

| Public path | Local service |
| --- | --- |
| `/socket.io/` (including WebSocket upgrades) | `http://127.0.0.1:4001` |
| `/realtime/health` | `http://127.0.0.1:4001/health` |
| All other paths | `http://127.0.0.1:8001` |

Start Laravel on port 8001 and the new realtime service on port 4001 first.
Then run these commands in separate terminals:

```powershell
cd C:\dev\CompliTrack\realtime
npm.cmd run proxy
```

```powershell
ngrok http http://127.0.0.1:8080
```

The proxy listens only on `127.0.0.1:8080`. Stop it with Ctrl+C when running
in a terminal. The current background proxy PID is recorded in
`storage/app/private/ngrok/proxy.pid`.

Set both values in `mobile/.env` to the public HTTPS origin, with no path:

```dotenv
COMPLITRACK_API_URL=https://undercook-ecosystem-detract.ngrok-free.dev
COMPLITRACK_REALTIME_URL=https://undercook-ecosystem-detract.ngrok-free.dev
```

Restart or rebuild Flutter after editing `.env`. Any `--dart-define` values
for these keys take precedence over `.env`; update those too if used.
Flutter adds `/api/v1` for API calls and uses `/socket.io/` for realtime.
The existing bearer-token authentication applies to both services.

Keep Laravel, realtime, the proxy, and ngrok running for mobile access.
If using Vite locally, public visitors must receive built frontend assets
instead of localhost Vite URLs. Keep Laravel's loopback proxy trust and
the ngrok hostname in `SANCTUM_STATEFUL_DOMAINS` for browser sessions.

The proxy port and upstreams can be overridden using
`COMPLITRACK_PROXY_PORT`, `COMPLITRACK_APP_UPSTREAM`, and
`COMPLITRACK_REALTIME_UPSTREAM` in the proxy process environment.
