<?php

declare(strict_types=1);

namespace Drupal\wse_deploy;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;

/**
 * The encryption handler.
 */
class EncryptionHandler {

  protected const HASH_LENGTH = 43;

  /**
   * The private key service.
   *
   * @var \Drupal\Core\PrivateKey
   */
  protected PrivateKey $privateKey;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * EncryptionHandler constructor.
   */
  public function __construct(PrivateKey $private_key, TimeInterface $time) {
    $this->privateKey = $private_key;
    $this->time = $time;
  }

  /**
   * Returns the hash for the specified data.
   *
   * @param string $data
   *   The data to be signed.
   *
   * @return string
   *   A base64-encoded HMAC hash.
   */
  public function getHash(string $data): string {
    return Crypt::hmacBase64($data, $this->getKey() . Settings::getHashSalt());
  }

  /**
   * Verifies the hash of the specified data.
   *
   * @param string $data
   *   The signed data.
   * @param string $hash
   *   A base64-encoded HMAC hash to be verified.
   *
   * @return bool
   *   TRUE if the hash is valid, FALSE otherwise.
   */
  public function validateHash(string $data, string $hash): bool {
    return hash_equals($this->getHash($data), $hash);
  }

  /**
   * Returns an expirable token.
   *
   * @param string ...$values
   *   Arbitrary string values to be used to make the token unique.
   *
   * @return string
   *   The generated token.
   */
  public function getExpirableToken(string ...$values): string {
    $timestamp = $this->time->getCurrentTime();
    $values[] = $timestamp;
    $data = implode(':', $values);
    return $this->getHash($data) . $timestamp;
  }

  /**
   * Validates an expirable token.
   *
   * @param string $token
   *   The token to be validated.
   * @param string ...$values
   *   The values used to generate the token.
   *
   * @return bool
   *   TRUE if the token is valid, FALSE otherwise.
   */
  public function validateExpirableToken(string $token, string ...$values): bool {
    $timestamp = (int) substr($token, static::HASH_LENGTH);
    $max_token_age = $timestamp + (int) Settings::get('wse_deploy.hash.expire', 10);

    if ($this->time->getRequestTime() <= $max_token_age) {
      $values[] = $timestamp;
      $data = implode(':', $values);
      $hash = substr($token, 0, static::HASH_LENGTH);
      return $this->validateHash($data, $hash);
    }

    return FALSE;
  }

  /**
   * Returns the key to be used to compute the hash.
   *
   * @return string
   *   The hash key.
   */
  protected function getKey(): string {
    // @todo Add support for the https://www.drupal.org/project/key module.
    return Settings::get('wse_deploy.hash.key') ?: $this->privateKey->get();
  }

}
