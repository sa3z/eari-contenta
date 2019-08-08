<?php  
/**  
 * @file  
 * Contains Drupal\welcome\Form\MessagesForm.  
 */  
namespace Drupal\passivate\Form;  
use Drupal\Core\Form\ConfigFormBase;  
use Drupal\Core\Form\FormStateInterface;  

class MessagesForm extends ConfigFormBase {  
	/**  
   * {@inheritdoc}  
   */  
  protected function getEditableConfigNames() {  
    return [  
      'passivate.adminsettings',  
    ];  
  }  

  /**  
   * {@inheritdoc}  
   */  
  public function getFormId() {  
    return 'passivate_form';  
  } 

   /**  
   * {@inheritdoc}  
   */  
 public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('passivate.adminsettings');

    $form['passivate_server_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server API URL'),
      '#description' => $this->t('Add Server API URL to Post the Content'),
      '#default_value' => $config->get('passivate_server_api_url'),
      '#size' => 60,
  '#maxlength' => 128,
  '#required' => TRUE,
    ];

    $form['server_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server API Key'),
      '#description' => $this->t('Add Server API URL Key to Post the Content'),
      '#default_value' => $config->get('server_api_key'),
      '#size' => 60,
  '#maxlength' => 128,
  '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('passivate.adminsettings')
      ->set('passivate_server_api_url', $form_state->getValue('passivate_server_api_url'))
      ->set('server_api_key', $form_state->getValue('server_api_key'))
      ->save();
  }
} 