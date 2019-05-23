<?php
/**
 * @file
 * Contains \Drupal\passivate\Controller\PassivateController.
 */
namespace Drupal\passivate\Controller;
class PassivateController {
  public function content() {
    return array(
      '#type' => 'markup',
      '#markup' => t('Hello, World!'),
    );
  }
}