<?php

namespace Drupal\wse_config;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\wse_config\Event\WseConfigEvents;
use Drupal\wse_config\Event\WseConfigOptOutEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The WSE config matcher service.
 */
class WseConfigMatcher {

  /**
   * The character user to match wildcards in config names.
   *
   * @var string
   */
  const CONFIG_WILDCARD_SUFFIX = '*';

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * An array of config names to be ignored.
   *
   * @var array
   */
  protected $ignored = [];

  /**
   * Constructs a WseConfigMatcher object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager) {
    $this->eventDispatcher = $event_dispatcher;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks whether given config names are ignored.
   *
   * @param array $configs
   *   The config names to check.
   *
   * @return bool
   *   TRUE if given configs are ignored.
   */
  public function configIsIgnored(array $configs) {
    return (bool) $this->getIgnoredConfigs($configs);
  }

  /**
   * Gets the names of config which are ignored.
   *
   * Modules can mark their config as ignored by subscribing to the event
   * defined in WseConfigEvents.
   *
   * @param array $configs_to_check
   *   The config names to check for ignored status.
   *
   * @return array
   *   List of names of ignored configs.
   */
  public function getIgnoredConfigs(array $configs_to_check) {
    $ignored = [];
    $configs_to_check = array_filter($configs_to_check);
    if ($configs_to_check) {
      $this->normalizeConfigNames($configs_to_check);

      foreach ($configs_to_check as $config_name) {
        foreach ($this->getIgnored() as $ignored_pattern) {
          if ($this->wildcardMatch($ignored_pattern, $config_name)) {
            $ignored[] = $config_name;
          }
        }
      }
    }
    else {
      $ignored = $this->getIgnored();
    }

    $ignored = array_map(function ($ignore_pattern) {
      return str_replace(static::CONFIG_WILDCARD_SUFFIX, '', $ignore_pattern);
    }, $ignored);
    return $ignored;
  }

  /**
   * Get ignored.
   *
   * @return array
   *   An array of config names to be ignored.
   */
  protected function getIgnored(): array {
    if (empty($this->ignored)) {
      /** @var \Drupal\wse_config\Event\WseConfigOptOutEvent $event */
      $event = $this->eventDispatcher->dispatch(
        new WseConfigOptOutEvent(),
        WseConfigEvents::WSE_CONFIG_OPT_OUT
      );
      $this->ignored = $event->getIgnored();
    }
    return $this->ignored;
  }

  /**
   * Gets a list of config entity types.
   *
   * Certain types get excluded as it doesn't make sense to edit those inside
   * a workspace for now.
   *
   * @return array
   *   The list of allowed entity types indexed by ID.
   */
  public function getAllowedConfigEntityTypes() {
    $allowed_entity_types = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    // @todo Can we determine this dynamically? The same goes for
    //   WseConfigSubscriber::onWseConfigOptOut() to stay consistent.
    $excluded = [
      'block_content_type',
      'field_config',
      'field_storage_config',
      'node_type',
      'user_role',
      'entity_form_display',
      'entity_view_display',
      'comment_type',
      'media_type',
      'pathauto_pattern',
      'base_field_override',
      'taxonomy_vocabulary',
      'view',
    ];

    foreach ($entity_types as $type) {
      if ($type instanceof ConfigEntityTypeInterface && !in_array($type->id(), $excluded)) {
        $allowed_entity_types[$type->id()] = (string) $type->getLabel();
      }
    }
    return $allowed_entity_types;
  }

  /**
   * Suffixes config prefixes with a wildcard character.
   *
   * @param array $configs
   *   Uniform list of config names or config names with wildcards.
   */
  protected function normalizeConfigNames(array &$configs) {
    foreach ($configs as &$config_name) {
      if (substr($config_name, -1) == '.') {
        $config_name = $config_name . static::CONFIG_WILDCARD_SUFFIX;
      }
    }
  }

  /**
   * Checks if a string matches a given wildcard pattern.
   *
   * @param string $pattern
   *   The wildcard pattern to me matched.
   * @param string $string
   *   The string to be checked.
   *
   * @return bool
   *   TRUE if $string string matches the $pattern pattern.
   */
  protected function wildcardMatch($pattern, $string) {
    $pattern = '/^' . preg_quote($pattern, '/') . '$/';
    $pattern = str_replace('\*', '.*', $pattern);
    return (bool) preg_match($pattern, $string);
  }

}
