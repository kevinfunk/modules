<?php

declare(strict_types=1);

namespace Drupal\wse_preview\Plugin\Validation\Constraint;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a given string is a valid preview expiry period.
 */
class ValidPreviewExpiryPeriodConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof ValidPreviewExpiryPeriodConstraint);
    $timestamp = strtotime(sprintf("+%s", $value));
    // If the timestamp is not valid or is in the past, add a violation.
    if (!$timestamp || $timestamp <= $this->time->getCurrentTime()) {
      $this->context->addViolation($constraint->message, ['@value' => $value]);
    }
  }

}
