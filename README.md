# MCP Project Tools Server

A secure, multi-user MCP (Model Context Protocol) server that integrates project management tools (Redmine, Jira) with AI assistants like Claude Desktop and Claude Code. Features OAuth2 authentication, encrypted credentials embedded in JWT tokens, and natural language interaction with your projects, issues, and time tracking.

## Features

- **Multi-Provider Support**: Connect to Redmine or Jira Cloud
- **OAuth2 Authentication**: Secure Google Sign-In for team authentication
- **Multi-user Support**: Each user has their own provider credentials
- **Stateless Architecture**: No database required - credentials encrypted in JWT tokens
- **Email Whitelist**: Domain-based access control (configurable)
- **HTTP Transport**: REST API with JWT tokens
- **Smart Time Tracking**: Natural language time logging with automatic summaries

## Quick Start

### For Users (Claude Desktop / Claude Code)

#### 1. Get the Server URL

Ask your administrator for:
- Server URL (e.g., `https://mcp.yourcompany.com`)
- Confirm your email is whitelisted

#### 2. Configure Claude Code

Create `.mcp.json` in your project or `~/.claude/.mcp.json`:

```json
{
  "mcpServers": {
    "redmine": {
      "type": "http",
      "url": "https://mcp.yourcompany.com/mcp"
    }
  }
}
```

#### 3. Authenticate

1. Run `/mcp` in Claude Code
2. First use will redirect you to Google Sign-In
3. After authentication, choose your provider (Redmine or Jira)
4. Provide your instance URL and API key (+ email for Jira)
5. Done! You can now interact with your projects

### For Administrators (Deployment)

#### Docker Deployment

```bash
# Clone and configure
git clone https://github.com/guiziweb/mcp-redmine.git
cd mcp-redmine
cp .env.example .env.local
# Edit .env.local with your settings

# Deploy
make deploy

# View logs
make docker-logs
```

#### GitHub Actions (Auto-deploy)

The repository includes a GitHub Actions workflow for automatic deployment to a VPS. Configure these secrets in your repository:

- `VPS_HOST`: Your server hostname
- `VPS_USER`: SSH username
- `VPS_SSH_KEY`: SSH private key
- `APP_SECRET`: Symfony app secret
- `JWT_SECRET`: JWT signing secret
- `APP_URL`: Your server URL
- `GOOGLE_CLIENT_ID`: Google OAuth client ID
- `GOOGLE_CLIENT_SECRET`: Google OAuth client secret
- `ENCRYPTION_KEY`: Sodium encryption key (base64)
- `ALLOWED_EMAIL_DOMAINS`: Allowed email domains

## Available Tools

| Tool | Description | Example Prompts |
|------|-------------|-----------------|
| **List Projects** | Show all accessible Redmine projects | "Show my projects", "List all projects" |
| **List Issues** | List issues from a specific project | "Show issues from Mobile App project", "List tickets in #123" |
| **Get Issue Details** | Get comprehensive issue information | "Show details of issue #456", "Get issue #789 with journals" |
| **List Time Entries** | Retrieve time entries with smart filtering | "Show my hours this week", "Time entries from August", "My daily average" |
| **Log Time** | Log time to an issue interactively | "Log 2 hours to issue #123", "Add time to ticket #456" |
| **List Activities** | Show available time entry activities | "Show activity types", "What activities can I log?" |

### Smart Features

- **Date Intelligence**: "last week", "this month", "August 2025"
- **Automatic Summaries**: Total hours, daily/weekly breakdowns
- **Work Analysis**: Hours per day, project breakdowns, patterns
- **Interactive**: Will ask for missing parameters

## Architecture

```
┌─────────────────────┐
│   Claude Client     │
│  (Desktop / Code)   │
└─────────┬───────────┘
          │ HTTP + JWT
          ▼
┌─────────────────────────────────────────┐
│   MCP Project Tools Server (Stateless)  │
│                                         │
│  ┌─────────────────────────────────┐    │
│  │  OAuth2 Controller              │    │
│  │  Google Sign-In + Email Check   │    │
│  └─────────────┬───────────────────┘    │
│                │                        │
│  ┌─────────────▼───────────────────┐    │
│  │  JWT with Encrypted Credentials │    │
│  │  Access: 24h / Refresh: 30d     │    │
│  └─────────────┬───────────────────┘    │
│                │                        │
│  ┌─────────────▼───────────────────┐    │
│  │  ProviderFactory                │    │
│  │  (Redmine / Jira)               │    │
│  └─────────────┬───────────────────┘    │
└────────────────┼────────────────────────┘
                 │
        ┌────────┴────────┐
        ▼                 ▼
┌───────────────┐ ┌───────────────┐
│  Redmine API  │ │   Jira API    │
└───────────────┘ └───────────────┘
```

**Key Components:**
- **OAuth2 Controller**: Handles Google authentication and email whitelist verification
- **JWT Tokens**: Access token (24h) + Refresh token (30 days) with encrypted provider credentials
- **ProviderFactory**: Creates the appropriate provider (Redmine or Jira) based on user credentials
- **Encryption**: Credentials encrypted with Sodium (XSalsa20-Poly1305) inside JWT

**Stateless Design:**
- No database required
- Credentials are encrypted and embedded in JWT tokens
- Users re-authenticate via Google every 30 days (refresh token expiry)

## Development

### Requirements

- PHP 8.2+
- Composer
- Sodium extension
- Docker (for deployment)

### Local Setup

```bash
# Clone repository
git clone https://github.com/guiziweb/mcp-redmine.git
cd mcp-redmine

# Install dependencies
composer install

# Configure environment
cp .env.template .env.local
# Edit .env.local with your settings

# Generate encryption key
php -r "echo base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)) . PHP_EOL;"

# Start development server
symfony server:start --port=8080
```

### Makefile Commands

```bash
make dev          # Install dev dependencies
make deploy       # Rebuild and restart Docker
make docker-logs  # View container logs
make test         # Run tests
make phpstan      # Static analysis
make cs-fix       # Fix code style
```

### Environment Variables

**Required:**
- `APP_URL`: Your server URL (ngrok for dev, custom domain for prod)
- `GOOGLE_CLIENT_ID`: From Google Cloud Console
- `GOOGLE_CLIENT_SECRET`: From Google Cloud Console
- `ENCRYPTION_KEY`: Base64-encoded 32-byte Sodium key
- `JWT_SECRET`: Random string for JWT signing

**Optional:**
- `APP_ENV`: `dev` or `prod` (default: `dev`)
- `ALLOWED_EMAIL_DOMAINS`: Comma-separated list of allowed email domains
- `ALLOWED_EMAILS`: Comma-separated list of specific allowed emails

### Bot Tokens (for automation)

Create a long-lived bot token with embedded credentials:

```bash
# For Redmine
docker exec mcp-redmine-app php bin/console app:create-bot \
  bot-name \
  https://redmine.example.com \
  your-redmine-api-key

# For Jira
docker exec mcp-redmine-app php bin/console app:create-bot \
  bot-name \
  https://your-instance.atlassian.net \
  your-jira-api-token \
  --provider=jira \
  --provider-email=your-email@company.com
```

### Testing

```bash
# Run all tests
make test

# Static analysis
make phpstan

# Code style
make cs-fix
```

## Google OAuth Configuration

### 1. Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create new project: "MCP Redmine"
3. Enable Google+ API

### 2. Configure OAuth Consent Screen

1. APIs & Services → OAuth consent screen
2. User Type: **External**
3. App name: "MCP Redmine"
4. Scopes: `email`, `profile` (non-sensitive, no validation required)

### 3. Create OAuth2 Credentials

1. APIs & Services → Credentials → Create Credentials → OAuth 2.0 Client ID
2. Application type: **Web application**
3. Authorized redirect URIs:
   - Development: `https://your-ngrok-url.ngrok-free.dev/oauth/google-callback`
   - Production: `https://mcp.yourcompany.com/oauth/google-callback`
4. Save and copy Client ID and Client Secret

### 4. Email Whitelist

Configure allowed users via environment variables:

```bash
# Allow entire domains (comma-separated)
ALLOWED_EMAIL_DOMAINS=yourcompany.com

# Allow specific emails (comma-separated)
ALLOWED_EMAILS=alice@example.com,bob@example.com
```

Both options can be combined. If neither is set, no users will be able to access the application.

## Security

- **Encryption**: Credentials encrypted with Sodium (XSalsa20-Poly1305) in JWT
- **Google OAuth**: Identity verified through Google
- **Email Whitelist**: Only authorized emails can access
- **HTTPS**: Required in production
- **Token Expiry**: Access tokens expire in 24h, refresh tokens in 30 days

## Troubleshooting

### "Email not authorized"
Your email is not in the whitelist. Contact your administrator.

### OAuth redirect fails
- Verify `APP_URL` matches your actual server URL
- Check Google Console redirect URIs match exactly
- Ensure OAuth2 credentials are correct in environment

### "Token expired"
- Access tokens expire after 24 hours
- Refresh tokens expire after 30 days
- Re-authenticate via Google to get new tokens

## License

MIT

## Related

- [Model Context Protocol](https://github.com/anthropics/mcp)
- [Claude Code](https://docs.anthropic.com/en/docs/claude-code)
- [Redmine API](https://www.redmine.org/projects/redmine/wiki/Rest_api)
- [Jira Cloud REST API](https://developer.atlassian.com/cloud/jira/platform/rest/v3/intro/)