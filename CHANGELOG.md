# Changelog

## v5.5.0 - 2026-07-14

### Added

- AI settings section in the admin panel:
  - AI profiles list;
  - AI profile card;
  - provider-specific AJAX settings;
  - model search;
  - saved connection test status;
  - basic AI statistics page.
- AI provider support for OpenRouter, OpenAI, Yandex Cloud AI Studio, SberCloud / GigaChat, and VK Cloud.
- Encrypted AI API key storage in `ee_ai_profiles`.
- User properties through role-assigned property sets:
  - new `user` property entity type;
  - new `ee_user_role_to_property_set` table;
  - properties tab in the user card;
  - property set assignment in the user role card.
- Public documentation page for server setup with Nginx and Apache2 examples.
- Public documentation page for AI settings.

### Changed

- Cron scheduler logging no longer writes a file log entry only because run history was pruned when all agents returned `noop`.
- User edit form now submits through `FormData`, allowing property fields with file/image values.
- Documentation now covers user properties, AI profiles, model cache, provider-specific settings, security rules for API keys, and cron logging behavior.
- Runtime debug output is now controlled by `ENV_DEBUG` instead of a hardcoded flag in `index.php`.

### Fixed

- Installer-generated configuration fallback version now matches the release version.
- New installations include the `user` entity type for properties and property values.
