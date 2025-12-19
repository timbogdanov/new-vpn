# Larastory VPN

Laravel + 3x-ui VPN service for Dokploy deployment.

## Features

- Telegram bot for VPN access management
- Automatic VPN account creation on /start
- Multi-language support (Russian/English)
- Quick domain switching for ISP block mitigation
- Docker Compose deployment for Dokploy

## Quick Start

### 1. Clone and Configure

```bash
git clone <your-repo-url>
cd larastory-vpn
cp .env.example .env
```

### 2. Update Environment Variables

Edit `.env` with your actual values:

```bash
# Required
APP_KEY=base64:...  # Generate with: php artisan key:generate --show
TELEGRAM_BOT_TOKEN=your_bot_token
XUI_USERNAME=admin
XUI_PASSWORD=your_secure_password

# Domain configuration
VPN_PRIMARY_DOMAIN=larastory.com
VPN_PANEL_DOMAIN=dashboard.larastory.com
```

### 3. Deploy to Dokploy

1. Add this repository to Dokploy as a Docker Compose project
2. Set environment variables in Dokploy
3. Deploy

### 4. Post-Deployment Setup

After deployment:

1. **Configure 3x-ui panel:**
   - Access `https://dashboard.larastory.com`
   - Login with default credentials (admin/admin)
   - Change the admin password
   - Create an inbound (VLESS + Reality recommended)

2. **Set Telegram webhook:**
   ```bash
   docker exec larastory-laravel php artisan telegram:set-webhook
   ```

3. **Test the bot:**
   - Send `/start` to your Telegram bot

## Domain Switching (ISP Block Mitigation)

When your domain gets blocked:

1. **Point new domain to server** (DNS A record)

2. **Update environment variables:**
   ```bash
   VPN_PRIMARY_DOMAIN=new-domain.com
   VPN_PANEL_DOMAIN=panel.new-domain.com
   ```

3. **Update docker-compose.yml** Traefik labels

4. **Restart services:**
   ```bash
   docker-compose up -d
   ```

5. **Configure SSL in 3x-ui** for the new domain

## Project Structure

```
larastory-vpn/
├── docker-compose.yml      # Dokploy deployment
├── Dockerfile              # Laravel container
├── docker/                 # Docker configs (nginx, php, supervisor)
├── app/
│   ├── DTO/               # Data Transfer Objects
│   ├── Exceptions/        # Custom exceptions
│   ├── Services/          # XuiService, LinkService
│   ├── Telegram/          # Bot commands and handlers
│   └── Http/Controllers/  # Web controllers
├── config/
│   ├── vpn.php            # VPN configuration
│   ├── services.php       # 3x-ui credentials
│   └── telegram.php       # Bot configuration
└── lang/                  # Translations (en, ru)
```

## API Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /health` | Health check |
| `GET /vpn-link?url=...` | VPN app redirect |
| `POST /telegram/webhook` | Telegram bot webhook |

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `XUI_HOST` | 3x-ui container name | `3x-ui` |
| `XUI_PORT` | 3x-ui panel port | `2053` |
| `XUI_USERNAME` | Panel username | - |
| `XUI_PASSWORD` | Panel password | - |
| `XUI_INBOUND_ID` | Inbound ID for clients | `1` |
| `TELEGRAM_BOT_TOKEN` | Bot API token | - |
| `VPN_PRIMARY_DOMAIN` | Main domain | `larastory.com` |
| `VPN_PANEL_DOMAIN` | 3x-ui domain | `dashboard.larastory.com` |
| `VPN_SUBSCRIPTION_PORT` | Subscription port | `2096` |

## License

MIT
