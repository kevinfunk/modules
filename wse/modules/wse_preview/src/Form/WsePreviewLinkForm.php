<?php

namespace Drupal\wse_preview\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element\PathElement;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form that generates a workspace preview link.
 */
class WsePreviewLinkForm extends FormBase implements WorkspaceSafeFormInterface, TrustedCallbackInterface {

  /**
   * The workspace for which a preview link will be generated.
   */
  protected WorkspaceInterface $workspace;

  /**
   * Constructor.
   */
  public function __construct(
    protected KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('keyvalue.expirable'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'wse_preview_link_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WorkspaceInterface $workspace = NULL): array {
    $this->workspace = $workspace;
    $form = [];

    $form['#prefix'] = '<div id="wse-preview-link-form">';
    $form['#suffix'] = '</div>';

    // Add a target for AJAX messages.
    $form['ajax_messages'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'ajax-messages',
      ],
      '#weight' => -10,
    ];

    $form['expiry'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Expiry time'),
      '#description' => $this->t("The time period should be specified as '8 hours', '1 day', '10 days, 12 hours', etc."),
      '#default_value' => $this->config('wse_preview.settings')->get('preview_expiry_default') ?? '8 hours',
      '#size' => 15,
    ];

    $form['redirect_url'] = [
      '#type' => 'path',
      '#convert_path' => PathElement::CONVERT_NONE,
      '#validate_path' => TRUE,
      '#title' => $this->t('Redirect URL'),
      '#description' => $this->t('An URL to redirect to when this preview is accessed.'),
      '#default_value' => $this->getRequest()->query->get('redirect_url'),
      '#maxlength' => 255,
    ];

    $existing_previews = $this->getExistingPreviews($workspace->id());
    $form['preview_link'] = [
      '#type' => 'textfield',
      '#title' => !$existing_previews ? $this->t('Preview link') : $this->t('Latest preview link'),
      '#disabled' => TRUE,
      '#allow_focus' => TRUE,
      '#size' => 70,
    ];
    $form['#pre_render'][] = [static::class, 'preRenderForm'];

    if ($existing_previews && !$form_state->isSubmitted()) {
      $form_state->set('preview_id', array_key_last($existing_previews));
      $preview = end($existing_previews);

      $form['existing_preview'] = [
        '#type' => 'item',
        '#markup' => $this->t('A preview link already exists for this workspace, and it expires in %expire.', [
          '%expire' => \Drupal::service('date.formatter')->formatTimeDiffUntil($preview['expire']),
        ]),
      ];
    }

    if ($form_state->has('preview_id')) {
      $form['preview_link']['#default_value'] = Url::fromRoute('wse_preview.workspace_preview', [
        'preview_id' => $form_state->get('preview_id'),
      ])->setAbsolute(TRUE)->toString();
    }

    $form['actions']['generate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => 'wse-preview-link-form',
      ],
    ];

    if ($existing_previews) {
      $form['actions']['delete_previews'] = [
        '#type' => 'submit',
        '#value' => $this->formatPlural(count($existing_previews), 'Delete one existing preview', 'Delete @count existing previews'),
        '#button_type' => 'danger',
        '#ajax' => [
          'callback' => '::ajaxRefresh',
          'wrapper' => 'wse-preview-link-form',
        ],
        '#submit' => ['::deletePreviewsSubmit'],
      ];
    }

    $form['#attached']['library'][] = 'wse_preview/preview-link-form';

    return $form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Replace the whole form.
    $response->addCommand(new ReplaceCommand('#wse-preview-link-form', $form));

    // Display form validation errors.
    $errors = \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
    foreach ($errors as $error) {
      $response->addCommand(new MessageCommand($error, '#ajax-messages', ['type' => 'error']));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $expiry = $form_state->getValue('expiry');
    $timestamp = strtotime(sprintf("+%s", $expiry));
    if (!$timestamp || $timestamp <= \Drupal::time()->getCurrentTime()) {
      $form_state->setErrorByName('expiry', $this->t('Invalid expiry time.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $key_value = $this->keyValueExpirableFactory->get('wse_preview');

    $key = Crypt::randomBytesBase64(20);
    $expire = strtotime(sprintf("+%s", $form_state->getValue('expiry')));
    $redirect_url = $form_state->getValue('redirect_url') ?? NULL;

    $value = [
      'workspace' => $this->workspace->id(),
      'expire' => $expire,
      'redirect_url' => $redirect_url,
    ];
    $key_value->setWithExpire($key, $value, $expire - $this->time->getRequestTime());

    $form_state->set('preview_id', $key);
    $form_state->setRebuild();
  }

  /**
   * Submission handler for the "Delete existing previews" button.
   */
  public function deletePreviewsSubmit(array $form, FormStateInterface $form_state): void {
    // Delete all existing previews for this workspace.
    if ($existing_previews = $this->getExistingPreviews($this->workspace->id())) {
      $key_value = $this->keyValueExpirableFactory->get('wse_preview');
      $key_value->deleteMultiple(array_keys($existing_previews));

      unset($form_state->getStorage()['preview_id']);
      $form_state->setRebuild();
    }
  }

  /**
   * Finds existing previews for a given workspace ID.
   *
   * @param string $workspace_id
   *   A workspace ID.
   *
   * @return array
   *   An array of existing previews data, keyed by preview ID.
   */
  protected function getExistingPreviews(string $workspace_id): array {
    $existing_previews = [];

    $key_value = $this->keyValueExpirableFactory->get('wse_preview');
    foreach ($key_value->getAll() as $preview_id => $entry) {
      if ($entry['workspace'] == $workspace_id) {
        $existing_previews[$preview_id] = $entry;
      }
    }

    return $existing_previews;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderForm'];
  }

  /**
   * Pre-render callback.
   */
  public static function preRenderForm($form) {
    $preview_link_id = $form['preview_link']['#id'];
    $module_path = \Drupal::moduleHandler()->getModule('wse_preview')->getPath();
    $label = t('Click to copy');

    $form['preview_link']['#field_suffix'] = <<<EOF
    <a class="clipboardjs-button" data-clipboard-target="#$preview_link_id">
      <span class="tooltip">
        <img src="/$module_path/images/clippy.svg" alt="$label" title="$label" height="16" width="16" />
        <span class="tooltiptext"></span>
      </span>
    </a>
EOF;

    return $form;
  }

}
