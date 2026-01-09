# MCP Redmine

MCP server connecting AI assistants (Claude, Cursor) to Redmine.

## Stack

- Symfony 7.4 / PHP 8.2+
- Doctrine ORM (SQLite)
- MCP SDK (`mcp/sdk`)
- OAuth 2.0 + Google Sign-In

## Architecture

```
src/
├── Mcp/                    # MCP Core
│   ├── Application/
│   │   ├── Tool/Redmine/   # 12 tools
│   │   └── Resource/       # MCP Resources
│   ├── Domain/
│   │   ├── Model/          # Issue, Project, TimeEntry, etc.
│   │   └── Port/           # Hexagonal interfaces
│   └── Infrastructure/
│       ├── Adapter/        # AdapterFactory, AdapterHolder
│       └── Provider/Redmine/
│
├── OAuth/                  # OAuth 2.0 Server
│   └── Infrastructure/
│       ├── Controller/     # /oauth/authorize, /oauth/token
│       └── Security/       # TokenService, CodeStore
│
└── Admin/                  # Multi-tenant Admin Panel
    └── Infrastructure/
        ├── Doctrine/Entity/  # User, Organization, McpSession, AccessToken, InviteLink
        ├── Controller/       # Dashboard, Users, Sessions, Invites
        ├── Security/Voter/   # Permissions
        └── Service/          # ToolRegistry
```

## MCP Tools

| Tool | Description |
|------|-------------|
| `list_projects` | List projects |
| `list_issues` | Assigned issues |
| `get_issue_details` | Details + comments + attachments |
| `update_issue` | Update status/assignee/% |
| `get_attachment` | Download attachment |
| `list_time_entries` | Time entries |
| `log_time` | Log time |
| `update_time_entry` | Update time entry |
| `delete_time_entry` | Delete time entry |
| `add_comment` | Add comment |
| `update_comment` | Update comment |
| `delete_comment` | Delete comment |

## MCP Resources

| Resource | Description | Used by |
|----------|-------------|---------|
| `provider://statuses` | Issue statuses | `update_issue`, `list_issues` |
| `provider://projects/{id}/activities` | Activity types | `log_time` |
| `provider://projects/{id}/members` | Project members | `update_issue` (assignment) |
| `provider://projects/{id}/wiki` | Wiki pages list | - |
| `provider://projects/{id}/wiki/{title}` | Wiki page content | - |

## Multi-tenancy

### Entities

- **Organization**: Redmine URL, enabledTools
- **User**: email, roles, providerCredentials (encrypted), enabledTools
- **McpSession**: MCP session with TTL (1h default)
- **AccessToken**: OAuth tokens (hashed)
- **InviteLink**: Invite links with expiration

### Roles

- `ROLE_USER`: standard user
- `ROLE_ORG_ADMIN`: org admin
- `ROLE_SUPER_ADMIN`: access to all orgs

### Tool Permissions

```php
// Org allows tool AND (user empty OR user allows)
$user->hasToolEnabled('log_time')
```

## Admin Panel

| Route | Description |
|-------|-------------|
| `/admin` | Dashboard (users, active sessions) |
| `/admin/users` | User management (approve, roles, tools) |
| `/admin/sessions` | Active MCP sessions |
| `/admin/invites` | Invite links |
| `/admin/organization` | Org config |

## Auth Flow

1. MCP Client → `/oauth/authorize`
2. → Google Sign-In
3. → Redmine API key
4. → `/oauth/token` → access_token + refresh_token
5. MCP Client → `/mcp` with Bearer token

## Configuration

```bash
# .env.local
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
ENCRYPTION_KEY=base64-sodium-key

# Google OAuth
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxx

# Access control (at least one)
ALLOWED_EMAIL_DOMAINS=company.com
ALLOWED_EMAILS=user@example.com
```

## Commands

```bash
make dev              # Install deps
make test             # PHPUnit
make static-analysis  # PHPStan + CS
make cs-fix           # Fix code style
make deploy           # Docker rebuild

# Create bot token
php bin/console app:create-bot \
  --organization=my-org \
  --email=bot@company.com \
  --api-key=xxx
```

## Local Dev

```bash
composer install
cp .env.example .env.local
# Configure .env.local

symfony server:start --port=8080
```

## Technical Notes

### Session TTL

MCP sessions expire after 1h of inactivity (`DoctrineSessionStore::$ttl = 3600`).

### Mixed Types

Numeric tool params use `mixed` + manual cast to workaround Cursor validation bug.

```php
#[Schema(description: 'Project ID')]
mixed $project_id = null,
// ...
$project_id = (int) $project_id;
```

### ToolRegistry

Auto-discovery via marker interface (`RedmineTool`).

### Encryption

Provider credentials encrypted with libsodium (`EncryptionService`).