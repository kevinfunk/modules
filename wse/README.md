Workspaces Extra
================

Provides various new functionalities for the core Workspaces module:

- New `status` field to Workspaces with open/closed value (Workspaces are closed once published)
- Clone a Workspace's metadata on publish
- Rollback changes from a closed Workspace
- Move content between Workspaces
- Discard changes in a Workspace
- Simplified Workspace switcher
- Revision squashing upon Workspace publish
- Allowlist for additional Workspace-safe forms
- Workspace-aware revisions listing page

Several submodules are also provided:

- **Workspaces config** (wse_config) - support for staging an allowlist of configuration changes
- **Workspaces deploy** (wse_deploy) - deploy Workspace content using an import/export system
- **Workspaces group access** (wse_group_access) - restrict Workspaces to groups of users
- **Workspaces layout builder** (wse_layout_builder) - Layout Builder tweaks for Workspaces
- **Workspaces menu** (wse_menu) - adds the ability to stage menu hierarchies in a Workspace
- **Workspaces preview** (wse_preview) - generate sharable Workspace preview links to external users
- **Workspaces scheduler** (wse_scheduler) - schedule Workspace publishing
