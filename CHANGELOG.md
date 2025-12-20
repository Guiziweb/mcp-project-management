# Changelog

## [1.1.0](https://github.com/Guiziweb/redmine-mcp/compare/v1.0.0...v1.1.0) (2025-12-20)


### Features

* add attachments and journals support for issues ([3c27906](https://github.com/Guiziweb/redmine-mcp/commit/3c279062e299b3208c620b8239413470291dcde3))


### Bug Fixes

* add missing LICENSE file ([f12b727](https://github.com/Guiziweb/redmine-mcp/commit/f12b72712627214a4bc1b0b4e9e796dc7961d295))
* use stable MCP SDK and add composer validate to CI ([1ff70f0](https://github.com/Guiziweb/redmine-mcp/commit/1ff70f0428842e4d077ce453fa47ab1573408d2d))

## 1.0.0 (2025-12-20)


### ⚠ BREAKING CHANGES

* Remove database dependency completely. Users must re-authenticate after this update.

### Features

* --stability=dev ([12bc47e](https://github.com/Guiziweb/redmine-mcp/commit/12bc47e000e11b7dd17e0405ba17074a5e0b6336))
* add .env.template and GitHub Actions deployment workflow ([7950c39](https://github.com/Guiziweb/redmine-mcp/commit/7950c39a4a09b0e98581f5a897040b48450a25a1))
* add admin bot support with role-based access control ([a3e1f9e](https://github.com/Guiziweb/redmine-mcp/commit/a3e1f9efb708dc132f41c2e55882062422b144aa))
* add automated deployment with GitHub Actions ([dec2cb4](https://github.com/Guiziweb/redmine-mcp/commit/dec2cb411c14b55e7df941871cef0d466a58353d))
* add composer patch for MCP protocol version 2024-11-05 ([b2247c3](https://github.com/Guiziweb/redmine-mcp/commit/b2247c3dc6baaec41a21eb651d5d4c80aa2db561))
* add GetIssueDetailsTool for comprehensive issue data ([d7c6d00](https://github.com/Guiziweb/redmine-mcp/commit/d7c6d000f2083bcc368def7d02be4082b6d4ffe0))
* add OAuth2/JWT authentication with Keycloak support ([aa338bd](https://github.com/Guiziweb/redmine-mcp/commit/aa338bd448310a3d2847307e8a7155d868bc46b6))
* Add pre-commit hook with quality checks ([5b30cf9](https://github.com/Guiziweb/redmine-mcp/commit/5b30cf93245567cf631961063783a0b2b4726c2e))
* improve README documentation and rename tool for consistency ([53980d3](https://github.com/Guiziweb/redmine-mcp/commit/53980d303316cfea22a02d1f681b32c12a9fee65))
* Initial commit - AI Redmine MCP Server ([73bc70e](https://github.com/Guiziweb/redmine-mcp/commit/73bc70ec85d45a188884204743a6ce7e78f0e664))
* MCP Redmine Server Alpha - First stable architecture ([8230c81](https://github.com/Guiziweb/redmine-mcp/commit/8230c8186b4ed47fa4145c1f95f5bf35a2ec0bb2))
* migrate to Doctrine ORM + PostgreSQL with OAuth2 ([d4bb356](https://github.com/Guiziweb/redmine-mcp/commit/d4bb3560731430c0837ca13fc50089f90f379711))
* refactor to stateless architecture with JWT-embedded credentials ([d9c2571](https://github.com/Guiziweb/redmine-mcp/commit/d9c257164e57ee06ca659a8e9233043e72677e0c))
* useless point ([a0a4eed](https://github.com/Guiziweb/redmine-mcp/commit/a0a4eedaba4145fa4a8332ca9a3951311bf112f8))


### Bug Fixes

* add PostgreSQL driver for Doctrine ([8f79e23](https://github.com/Guiziweb/redmine-mcp/commit/8f79e23fe7c661f168c94b0a59f3947e576ac55b))
* change user_id parameter type from string to int for Redmine API compatibility ([52f3a62](https://github.com/Guiziweb/redmine-mcp/commit/52f3a62193298b2d534eb39a456d72bf9b2169d7))
* commit .env file for Symfony Docker deployment ([895dfb4](https://github.com/Guiziweb/redmine-mcp/commit/895dfb45384a8991124ab45783cb6b328b151850))
* ensure var/ permissions are correct for www-data after cache clear ([fe25be5](https://github.com/Guiziweb/redmine-mcp/commit/fe25be594476fcf74e94024875f1187f962a612f))
* move cache:clear to runtime to avoid build errors ([6fad733](https://github.com/Guiziweb/redmine-mcp/commit/6fad7334de286d446c20d1f1d4c0150008d04687))
* remove || true from cache:clear to catch build failures ([0531e8b](https://github.com/Guiziweb/redmine-mcp/commit/0531e8bf6a68bc247b432657f45d39e71877c468))
* remove final keyword from UserCredential for Doctrine proxies ([49cc410](https://github.com/Guiziweb/redmine-mcp/commit/49cc410cbaf1923f559da390c3cd82b1d9ae5ff0))
* run database migrations on container startup ([c1714ce](https://github.com/Guiziweb/redmine-mcp/commit/c1714ce8b6f7757557794123b95b8e06b374e473))
* upgrade to PHP 8.4 to support .well-known routes ([de32367](https://github.com/Guiziweb/redmine-mcp/commit/de323672c47504f16bcadaa837afaa6e36d4c9f5))
* use findByUserId instead of find in CreateBotCommand ([9fc6025](https://github.com/Guiziweb/redmine-mcp/commit/9fc6025bb5ae6ca48619eb347c7e8cfbb7d570d5))
* use findByUserId instead of find in OAuthController ([f830213](https://github.com/Guiziweb/redmine-mcp/commit/f830213807d8baac659f91b81172c6535c4a9c11))
* use PostgreSQL types in migration (TEXT instead of CLOB) ([fd908a0](https://github.com/Guiziweb/redmine-mcp/commit/fd908a00f792e7da02c4a118a9cc9c1b88f246bd))


### Miscellaneous Chores

* add .dockerignore and docker commands to Makefile ([40c9104](https://github.com/Guiziweb/redmine-mcp/commit/40c91046ac54501069db5cf36002337653fabd62))
* ignore .claude directory ([337b641](https://github.com/Guiziweb/redmine-mcp/commit/337b641ada8785bedc8efd55abc8c58023e8fc6b))
* remove database from docker-compose ([8cd2fa4](https://github.com/Guiziweb/redmine-mcp/commit/8cd2fa4466e9264e8cefb5229b4d75afa42ec9bf))
