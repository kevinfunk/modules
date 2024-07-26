# Content Planner

Drupal Content Planner helps you to create, plan and manage content. It offers
a dashboard, a content calendar and a todo list. It's completely open source and
free to use.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/content_planner).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/content_planner).


## Table of contents

- Requirements
- Installation
- Configuration
- Module development
- Maintainers


## Requirements

This module requires the following outside of Drupal core:

- [Scheduler](https://www.drupal.org/project/scheduler)

Only node entities with Content Moderation enabled are supported at the moment.
Additionally, Content Calendar requires the Scheduler module to be enabled for 
any node types that will be used with the module.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Navigate to Administration > Extend and enable the module.
2. Once enabled, there is a new tab "Content Planner" in the Admin bar with
   three choices: Dashboard, Content Calendar, and Content Kanban.
3. The dashboard shows and overview of the available widgets. The dashboard
   is flexible and configurable. Create a widget using the Drupal plugin
   system.
4. The Content Calendar is a calendar view of all the site's content.
   Content can be directly added from this view.
5. The Content Kanban is a todo list that lists the workflow states in
   columns.

However you need to configure the content planner according to your specific use
case. Please follow the instructions here:
`https://www.drupal-blog.ch/drupal-module/how-install-drupal-content-calendar`.


## Module development

To compile module scss files use npm.

```
npm install
npm run build-css
```

## Maintainers

- Yannick Bättig - [yogglio](https://www.drupal.org/u/yogglio)
- Lukas Fischer - [lukas.fischer](https://www.drupal.org/u/lukasfischer)
- martinpe - [martinpe](https://www.drupal.org/u/martinpe)
- Natalia J. - [nataliajustice](https://www.drupal.org/u/nataliajustice)
- Nida Shah - [nidaismailshah](https://www.drupal.org/u/nidaismailshah)
- Dieter Holvoet - [DieterHolvoet](https://www.drupal.org/u/dieterholvoet)

**Supporting organizations:**

- [NETNODE AG](https://www.drupal.org/netnode-ag)
