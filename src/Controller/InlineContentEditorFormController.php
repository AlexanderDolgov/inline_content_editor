<?php

namespace Drupal\rai_inline_content_editor\Controller;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\rai_inline_content_editor\Access\InlineContentEditorAccessCheck;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class InlineContentEditorFormController.
 */
class InlineContentEditorFormController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Form\FormBuilderInterface definition.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Drupal\Core\Logger\LoggerChannelInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * InlineContentEditorAccessCheck definition.
   *
   * @var \Drupal\rai_inline_content_editor\Access\InlineContentEditorAccessCheck
   */
  protected $accessChecker;

  /**
   * Constructs a new UserRelationAPIController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   * @param \Drupal\rai_inline_content_editor\Access\InlineContentEditorAccessCheck $access_checker
   *   Access checking service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelInterface $logger,
    FormBuilderInterface $formBuilder,
    InlineContentEditorAccessCheck $access_checker
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->formBuilder = $formBuilder;
    $this->accessChecker = $access_checker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.channel.default'),
      $container->get('form_builder'),
      $container->get('rai_inline_content_editor.access_checker')
    );
  }

  /**
   * Renders form in a modal window.
   *
   * @param string $entity_type_id
   *   The entity type ID of the form.
   * @param string|int $entity_id
   *   Entity ID.
   * @param string $form_display_id
   *   Form display ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\JsonResponse
   *   Return AjaxResponse on success or JsonResponse on error.
   */
  public function getEntityForm($entity_type_id, $entity_id, $form_display_id) {
    // Get storage for a given entity type.
    try {
      $entity_storage = $this->entityTypeManager()->getStorage($entity_type_id);
    }
    catch (\Exception $e) {
      return $this->processError($e->getMessage());
    }

    // Load an entity by a given ID.
    $entity = $entity_storage->load($entity_id);

    if (!$entity) {
      $error_message = sprintf('Unable to load entity with id "%s".', $entity_id);
      return $this->processError($error_message);
    }

    // Check if user allowed to use Inline Content Editor.
    $access_result = $this->accessChecker->useInlineContentEditor($entity);
    if ($access_result instanceof AccessResultForbidden) {
      $error_message = sprintf('User is not allowed to use Inline Content Editor for entity "%s".', $entity_id);
      return $this->processError($error_message);
    }

    // Load form for a given entity type and form display.
    $form = $this->entityTypeManager()
      ->getFormObject($entity_type_id, $form_display_id)
      ->setEntity($entity);

    if (!$form) {
      $error_message = sprintf('Unable to load form with id "%s".', $form_display_id);
      return $this->processError($error_message);
    }

    // Create a render array of the form.
    $form_render_array = $this->formBuilder->getForm($form);

    if (!$form_render_array) {
      $error_message = sprintf('Unable to get render array for the form with id "%s".', $form_display_id);
      return $this->processError($error_message);
    }

    // List fields to hide on the form.
    $fields_to_hide = [
      'advanced',
      'footer',
      'status',
    ];

    // Hide fields on the form.
    foreach ($fields_to_hide as $field) {
      if (isset($form_render_array[$field])) {
        $form_render_array[$field]['#access'] = FALSE;
      }
    }

    // Hide "Preview" and "Delete" button,
    // so it won't display in the modal dialog window.
    $form_render_array['actions']['preview']['#access'] = FALSE;
    $form_render_array['actions']['delete']['#access'] = FALSE;

    // Settings for modal dialog window.
    $modal_dialog_title = sprintf('Update: %s', $entity->label());
    $modal_dialog_options = [
      'width' => '900',
      'height' => '650',
    ];

    // Create an AjaxResponse to open the form in a modal window
    // with the given settings.
    $response = new AjaxResponse();
    $response->addCommand(new PrependCommand('body', '<div id="inline-content-editor-dialog"></div>'));
    $response->addCommand(new OpenDialogCommand('#inline-content-editor-dialog', $modal_dialog_title, $form_render_array, $modal_dialog_options));

    return $response;
  }

  /**
   * Processes a given error.
   *
   * Currently it logs error to the dblog and returns JsonResponse
   * containing this error.
   *
   * @param string $error_message
   *   Error message.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JsonResponse object (to be used in controllers).
   */
  protected function processError($error_message) {
    // Log the error in dblog.
    $this->logger->error($error_message);

    // Return a JsonResponse object.
    return new JsonResponse(['error' => $error_message]);
  }

}
