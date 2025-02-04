<?php

declare(strict_types=1);

namespace Drupal\wse_preview\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that the value of default preview expiry period is valid.
 */
#[Constraint(
  id: 'ValidPreviewExpiryPeriod',
  label: new TranslatableMarkup('Valid preview expiry period.', [], ['context' => 'Validation'])
)]
class ValidPreviewExpiryPeriodConstraint extends SymfonyConstraint {

  /**
   * The error message.
   */
  public string $message = "The time period '@value' is not valid. Some valid values would be '8 hours', '1 day', '10 days, 12 hours', etc.";

}
