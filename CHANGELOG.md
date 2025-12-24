# Changelog

## [1.5.0](https://github.com/Guiziweb/mcp-project-management/compare/v1.4.0...v1.5.0) (2025-12-24)


### Features

* add status management, filtering and issue update ([aeef0e7](https://github.com/Guiziweb/mcp-project-management/commit/aeef0e7d2b92559eb0dc1083d49dd3253f789b20))
* **tools:** add comment management tools ([d2efd27](https://github.com/Guiziweb/mcp-project-management/commit/d2efd2720adce6f05d9cca3c69a0285e7c234f6c))


### Bug Fixes

* **ci:** copy .env file for static-analysis job ([5e1aa37](https://github.com/Guiziweb/mcp-project-management/commit/5e1aa37f2ff9d2e8ac8c9ce766672eeecf52883d))
* **oauth:** add redirect_uri whitelist validation ([6f4f0d5](https://github.com/Guiziweb/mcp-project-management/commit/6f4f0d56e58afc00f2c95bef460adcca8ed65614))


### Code Refactoring

* **oauth:** redesign connection screen ([c65efe5](https://github.com/Guiziweb/mcp-project-management/commit/c65efe50a6836e8af3ce904a5c3a0cc824efc15a))


### Miscellaneous

* add Docker dev workflow with volume mounts ([e685068](https://github.com/Guiziweb/mcp-project-management/commit/e685068d310c45d04a4e652dce7f79a56603dd7f))

## [1.4.0](https://github.com/Guiziweb/mcp-project-management/compare/v1.3.0...v1.4.0) (2025-12-23)


### Features

* add Monday.com integration with dynamic tool filtering ([5ebfbf1](https://github.com/Guiziweb/mcp-project-management/commit/5ebfbf116d1c0aa0242137ed71912f5ecda1c1f4))


### Bug Fixes

* accept string activity_id from MCP clients ([efbf1cd](https://github.com/Guiziweb/mcp-project-management/commit/efbf1cdd133f67d3773e366e520a4d1972b73f25))
* **jira:** use renderedFields for HTML description instead of ADF object ([b15df50](https://github.com/Guiziweb/mcp-project-management/commit/b15df5095288bc496f5b8b00b6a2f77e805bc117))
* update composer.lock hash ([7890be9](https://github.com/Guiziweb/mcp-project-management/commit/7890be936585456a8130ce760d542430d424ffca))


### Code Refactoring

* align architecture with hexagonal/DDD conventions ([2cff71f](https://github.com/Guiziweb/mcp-project-management/commit/2cff71f78265384c195b5afd26062daa55e0103c))
* reorganize Domain layer by DDD aggregate ([f1295bf](https://github.com/Guiziweb/mcp-project-management/commit/f1295bf3f7128b112661800909fc9d0a8a11bf76))
* replace ListActivitiesTool with MCP resource + instructions ([16b6da0](https://github.com/Guiziweb/mcp-project-management/commit/16b6da073679d36943ec6acdc5080f0d5a35c211))
* split TimeTrackingPort into segregated interfaces (ISP) ([1509795](https://github.com/Guiziweb/mcp-project-management/commit/1509795fe9ec9b8f78f8c8ea624b209e3b451c07))


### Miscellaneous

* configure release-please to show refactor commits ([3ec3e9c](https://github.com/Guiziweb/mcp-project-management/commit/3ec3e9cb90d827fd8a197b8b1687bc4a76f2ef0c))
* rename repo to mcp-project-management ([78bd523](https://github.com/Guiziweb/mcp-project-management/commit/78bd52330c407b32e051bb485c1c28f97ac847e8))

## [1.3.0](https://github.com/Guiziweb/redmine-mcp/compare/v1.2.6...v1.3.0) (2025-12-20)


### Features

* **jira:** add attachment support ([8182baf](https://github.com/Guiziweb/redmine-mcp/commit/8182bafce031719c2ee5401af272efc5f29a49e2))


### Miscellaneous Chores

* add lint commands to Makefile and simplify CI ([600f47d](https://github.com/Guiziweb/redmine-mcp/commit/600f47dc377ea954da64ad36195ef983786c7a9b))

## [1.2.6](https://github.com/Guiziweb/redmine-mcp/compare/v1.2.5...v1.2.6) (2025-12-20)


### Bug Fixes

* allow .well-known paths in nginx for OAuth discovery ([e5e4609](https://github.com/Guiziweb/redmine-mcp/commit/e5e4609d671eb2332b33ede057309e19abed1be0))

## [1.2.5](https://github.com/Guiziweb/redmine-mcp/compare/v1.2.4...v1.2.5) (2025-12-20)


### Bug Fixes

* disable form CSRF for LiveComponent + configurable APP_ENV ([77dc761](https://github.com/Guiziweb/redmine-mcp/commit/77dc7615f987f659c4764c67661f6e0fb99cda83))

## [1.2.4](https://github.com/Guiziweb/redmine-mcp/compare/v1.2.3...v1.2.4) (2025-12-20)


### Bug Fixes

* run composer scripts with APP_ENV=prod in Docker build ([b1ad3d3](https://github.com/Guiziweb/redmine-mcp/commit/b1ad3d3fefd2e2dcff3b2382eb7d0c9909a053f0))

## [1.2.3](https://github.com/Guiziweb/redmine-mcp/compare/v1.2.2...v1.2.3) (2025-12-20)


### Bug Fixes

* add asset-map:compile for production assets ([3d2cae0](https://github.com/Guiziweb/redmine-mcp/commit/3d2cae0367b74acb40c4bf2409a8adc7a8c206cf))
* LiveComponent form submission and session expiry handling ([de6b13c](https://github.com/Guiziweb/redmine-mcp/commit/de6b13c046bcb462014af05e4213f562550a81e2))

## [1.2.2](https://github.com/Guiziweb/redmine-mcp/compare/v1.2.1...v1.2.2) (2025-12-20)


### Bug Fixes

* add ALLOWED_EMAILS to deploy workflows and env template ([76a3edb](https://github.com/Guiziweb/redmine-mcp/commit/76a3edb8efa355f9e419917c984d56686561e386))
* run composer post-install scripts after copying app files ([d92cf57](https://github.com/Guiziweb/redmine-mcp/commit/d92cf57437ab1e1890d46992ae2f02005f69baa6))

## [1.2.1](https://github.com/Guiziweb/redmine-mcp/compare/v1.2.0...v1.2.1) (2025-12-20)


### Bug Fixes

* add WWW-Authenticate header for OAuth discovery ([bfbb5cd](https://github.com/Guiziweb/redmine-mcp/commit/bfbb5cd52b4071925f00bd68f1de1248dbc2bcae))

## [1.2.0](https://github.com/Guiziweb/redmine-mcp/compare/v1.1.0...v1.2.0) (2025-12-20)


### Features

* add multi-provider support (Redmine + Jira) ([fa32b50](https://github.com/Guiziweb/redmine-mcp/commit/fa32b507a8f44f72a998d8677df473b59fbc77ac))
* add update and delete time entry tools ([0f3aae7](https://github.com/Guiziweb/redmine-mcp/commit/0f3aae7d05d4b302ad7babe57261818ae695e526))
* redesign OAuth page with Tailwind dark mode ([2217990](https://github.com/Guiziweb/redmine-mcp/commit/22179901448f8740b19ed3fb7b581eecf36b1711))
* replace JS with Symfony UX Live Components for provider form ([26bf82a](https://github.com/Guiziweb/redmine-mcp/commit/26bf82a582dcfa651940890cbf54368ddac9b2bd))

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
