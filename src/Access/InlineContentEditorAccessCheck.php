<?php

namespace Drupal\rai_inline_content_editor\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides access checks for Inline Content Editor module.
 */
class InlineContentEditorAccessCheck {

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new UserRelationAccessCheck object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user object.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * A custom access check for using the Inline Content Editor feature.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Any entity can be provided here.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Instance of the AccessResultAllowed or AccessResultForbidden class.
   */
  public function useInlineContentEditor(EntityInterface $entity) {
    // Allow if the user allowed to edit the current entity.
    if ($entity->access('update', $this->currentUser, TRUE) instanceof AccessResultAllowed) {
      return AccessResult::allowed();
    }

    // If above didn't do it deny access.
    return AccessResult::forbidden();
  }

}
