<?php

namespace Drupal\rai_inline_content_editor;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\rai_inline_content_editor\Access\InlineContentEditorAccessCheck;

/**
 * Provides a render_update_button() function to Twig.
 */
class RenderUpdateButtonTwigExtension extends \Twig_Extension {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * InlineContentEditorAccessCheck definition.
   *
   * @var \Drupal\rai_inline_content_editor\Access\InlineContentEditorAccessCheck
   */
  protected $accessChecker;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * RenderUpdateButtonTwigExtension constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route matching service.
   * @param \Drupal\rai_inline_content_editor\Access\InlineContentEditorAccessCheck $access_checker
   *   Access checking service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    RouteMatchInterface $route_match,
    InlineContentEditorAccessCheck $access_checker,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->routeMatch = $route_match;
    $this->accessChecker = $access_checker;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'rai_inline_content_editor.render_update_button';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('render_update_button', [$this, 'renderUpdateButton']),
    ];
  }

  /**
   * Returns Update link with a generated URL.
   *
   * @param string $entity_type_id
   *   The entity type ID of the form.
   * @param string $form_display_id
   *   Form display ID.
   *
   * @return array
   *   Render array for the created link.
   */
  public function renderUpdateButton($entity_type_id, $form_display_id) {
    // Get entity ID from the route parameters.
    $entity = $this->routeMatch->getParameter($entity_type_id);

    // If the current page is a node page.
    if ($entity instanceof Node) {
      $entity_id = $entity->id();
    }
    // If this is an AJAX request to update the page's content.
    else {
      $entity_id = $this->routeMatch->getParameter('entity_id');
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity = $storage->load($entity_id);
    }

    // Check if user allowed to use Inline Content Editor.
    $access_result = $this->accessChecker->useInlineContentEditor($entity);
    if ($access_result instanceof AccessResultForbidden) {
      return [];
    }

    // Define the link configuration.
    $text = Markup::create('<span>' . $this->t('Update') . '</span>');
    $route_name = 'rai_inline_content_editor.inline_content_editor_form_controller_get_entity_form';
    $url_parameters = [
      'entity_type_id' => $entity_type_id,
      'entity_id' => $entity_id,
      'form_display_id' => $form_display_id,
    ];

    // Create a Link object and get renderable array for this object.
    $update_link = Link::createFromRoute($text, $route_name, $url_parameters);
    $update_link = $update_link->toRenderable();

    // Add required classes to the link.
    $update_link['#attributes'] = [
      'class' => [
        'use-ajax',
        'inline-content-editor-button',
        'button',
        'button-update',
      ],
    ];

    return $update_link;
  }

}
