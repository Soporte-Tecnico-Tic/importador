<?php

namespace Drupal\content_import\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
Use Drupal\taxonomy\Entity\Vocabulary;
Use Drupal\content_import\Controller\BatchController;
/**
 * Configure Add Content Import settings for this site.
 */
class AddContentImport extends FormBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var mixed
   */
  protected $entityTypeManager;


  public function __construct() {
    $this->connection = \Drupal::database();
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'content_import_form';
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $id
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL)
  {
    $result = NULL;
    $array_fields = [];

    //if id exists then it loads and set value in fields form
    if($id){
      $form_state->set('id',$id);
      $query =  \Drupal::database()->select('content_import_list', 'r')
        ->condition('r.id', $id)
        ->fields('r')
        ->execute();
      $result = $query->fetch();
      $array_fields = json_decode($result->fields, true);

      $form_state->setValue('element_type', $result->type_element );
      $form_state->setValue('options', $result->element );

      if(!$form_state->get('n_fields')){
        $form_state->set('n_fields',sizeof($array_fields));
      }
    }

    $form['name-csv'] = [
      '#type' => 'fieldset',
    ];
    $form['name-csv']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('importer name'),
     // '#required' => TRUE,
      '#default_value' => $result ? $result->name : ''
    ];
    $form['name-csv']['file_csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File csv'),
      '#upload_location' => 'public://content-importer/',
      '#upload_validators' => array(
        'file_validate_extensions' => array('csv'),
        'file_validate_size' => 1024,
      ),
      '#default_value' => $result ? [$result->file] : NULL
    ];

    $form['type'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="options-content">',
      '#suffix' => '</div>',
    ];
    $form['type']['element_type'] = [
      '#type' => 'select',
      '#title' => $this->t('element type'),
      '#options' => [
        'none' => 'Select an option',
        'node' => $this->t('Content'),
        'user' => $this->t('User'),
        'taxonomy_term' => $this->t('taxonomy'),
      ],
      '#default_value' => $form_state->getValue('element_type'),
      '#ajax' => [
        'callback' => '::addOPtions',
        'event' => 'change',
        'wrapper' => 'options-content',
      ]
    ];

    $_SESSION['import_options'] = $form_state->getValue('options') == 'none' || NULL ? $_SESSION['import_options'] :  $form_state->getValue('options');
    $_SESSION['import_element_type'] = $form_state->getValue('element_type') == 'none' || NULL ? $_SESSION['import_element_type'] : $form_state->getValue('element_type');

    $form['type']['options'] = [
      '#type' => 'select',
      '#title' => $this->t('available options'),
      '#options' => $this->addOptionToSelect($_SESSION['import_element_type']),
      '#default_value' => $form_state->getValue('options'),
      '#ajax' => [
        'callback' => '::addOPtionsToFields',
        'event' => 'change',
        'wrapper' => 'fields-fieldset-wrapper',
      ]

    ];

    $form['fields'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="fields-fieldset-wrapper">',
      '#suffix' => '</div>',
      '#id_fields' => []
    ];

    //get options to id
    $options = $this->loadIdFields($_SESSION['import_element_type'], $_SESSION['import_options']);

    $elements_removed = $form_state->get('elements_removed');
    if($elements_removed == NULL){
      $elements_removed = [];
      $form_state->set('elements_removed',$elements_removed );
    }

    $num_fields = intval($form_state->get('n_fields'));
    if($num_fields == NULL){
      $num_fields = 2;
      $form_state->set('n_fields', $num_fields);
    }

    for ($i = 0; $i < $num_fields; $i++) {
      if(!in_array($i,$elements_removed)){
        $form['fields'][$i] = [
          '#type' => 'fieldset',
          '#title' => $this->t('field '.$i),
        ];
        $form['fields'][$i]['id_field-'.$i] = [
          '#type' => 'select',
          '#title' => $this->t('field ID'),
          '#options' => $options,

          '#default_value' => $array_fields ? $array_fields[$i]['id'] : '',
        ];
        $form['fields'][$i]['name_field-'.$i] = [
          '#type' => 'textfield',
          '#title' => $this->t('excel column'),
          '#default_value' => $array_fields ? $array_fields[$i]['value']: '',
        ];
        $form['fields'][$i]['remove_field-'.$i] = [
          '#type' => 'submit',
          '#iterator' => $i,
          '#name' => 'remove'.$i,
          '#submit' => ['::removeFieldSubmit'],
          '#value' => $this->t('remove Field'),
          '#ajax' => [
            'callback' => '::removeFieldCallback',
            'wrapper' => 'fields-fieldset-wrapper',
          ],
        ];

      }

    }

    $form['fields']['add_field'] = [
        '#type' => 'submit',
        '#submit' => ['::addFieldSubmit'],
        '#value' => $this->t('Add Field'),
        '#ajax' => [
          'callback' => '::addFieldCallback',
          'wrapper' => 'fields-fieldset-wrapper',
        ],
      ];


    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    );
    $form['actions']['submit2'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
      '#submit' => ['::importSubmit'],
    ];
    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {}

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $values = $form_state->getValues();
    if ($file = File::load($values['file_csv'][0])) {
      $file->setPermanent();
      $file->save();
    }

    $insert['file'] =  $file->id();
    $insert['name'] = $values['name'];
    $insert['type_element'] = $values['element_type'];
    $insert['element']  = $values['options'];

    $num_fields = $form_state->get('n_fields');
    $elements_removed = $form_state->get('elements_removed');
    $fields =[];
    for ($i = 0; $i < $num_fields; $i++) {
      if(!in_array($i,$elements_removed)){
        $fields[] = ['id' => $values['id_field-'.$i], 'value' => $values['name_field-'.$i]];
      }
    }
    $insert['fields']  = json_encode($fields);
    $insert['updated']  = time();
    $insert['data_import']  = 0;
    $insert['last_import']  = 0;

    $id = $form_state->get('id');
    if($id){
      $this->connection->update('content_import_list')
        ->fields($insert)
        ->condition('id', $id)
        ->execute();
    }else{
      $this->connection->insert('content_import_list')
        ->fields($insert)
        ->execute();
    }



    $url = Url::fromRoute('content_import.settings');
    $form_state->setRedirectUrl($url);
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function importSubmit(array &$form, FormStateInterface $form_state)
  {
    if ($file = File::load($form_state->getValue('file_csv')[0])) {
      $file->setPermanent();
      $file->save();
      $inputFileName = \Drupal::service('file_system')->realpath($file->getFileUri());

      $data = BatchController::csvtoarray_validate($inputFileName);

      if (empty($data)) {
        \Drupal::messenger()->addError($this->t('el archivo esta vacio o no tiene la estructura'));
      }else{
        $_SESSION['id_current_importer'] = $form_state->get('id');
        $_SESSION['data_saved'] = NULL;

        $values = $form_state->getValues();
        $num_fields = $form_state->get('n_fields');
        $elements_removed = $form_state->get('elements_removed');
        $fields =[];
        for ($i = 0; $i < $num_fields; $i++) {
          if(!in_array($i,$elements_removed)){
            $fields[] = ['id' => $values['id_field-'.$i], 'value' => $values['name_field-'.$i]];
          }
        }
        foreach ($data as $file) {
          $operations[] = [
            '\Drupal\content_import\Controller\BatchController::process_files',
            [
              $file,
              $fields,
             $values['options']
            ]
          ];
        }

      }
    }

    $batch = [
      'title' => t('Guardando data...'),
      'operations' => $operations,
      'init_message' => t('Guardando data esta empezando.'),
      'finished' => '\Drupal\content_import\Controller\BatchController::process_item_file_data_finish',
    ];

    batch_set($batch);

  }

  /**
   * Ajax Callback handler that will return the form structure
   */
  public function addFieldCallback(array &$form, FormStateInterface &$form_state) {
    return $form['fields'];
  }
  /**
   * Ajax submit handler that will return the form structure
   */
  public function addFieldSubmit(array &$form, FormStateInterface &$form_state) {
    $_SESSION['import_element_type'] = $form_state->getValue('element_type');
    $_SESSION['import_options'] = $form_state->getValue('options') ;

    $num_fields = intval($form_state->get('n_fields'));
    $num_fields++;
    $form_state->set('n_fields', $num_fields);
    $form_state->setRebuild();
  }


  /**
   * Ajax submit Callback that will return the form structure
   */
  public function removeFieldCallback(array &$form, FormStateInterface &$form_state) {
    return $form['fields'];

  }
  /**
   * Ajax submit handler that will return the form structure
   */
  public function removeFieldSubmit(array &$form, FormStateInterface &$form_state) {
    $_SESSION['import_element_type'] = $form_state->getValue('element_type');
    $_SESSION['import_options'] = $form_state->getValue('options') ;

    $element = intval($form_state->getTriggeringElement()['#iterator']);
    $elements_removed = $form_state->get('elements_removed');

    if( !in_array($element,$elements_removed)) array_push($elements_removed,$element);

    $form_state->set('elements_removed', $elements_removed);
    $form_state->setRebuild();
  }


  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function addOPtions(array &$form, FormStateInterface &$form_state) {
    return $form['type'];
  }


  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function addOPtionsToFields(array &$form, FormStateInterface &$form_state) {
    return $form['fields'];
  }


  /**
   * @param $options
   * @param $bundle
   *
   * @return array|string[]
   */
  function loadIdFields($options, $bundle){
    $none = ['none' => 'Select an option'];
    if (($bundle == 'none' || $options  == 'none') || ($bundle == NULL || $options  == NULL)) {
      return  $none ;
    }else{
      if($options == 'user'){
        $bundle = 'user';
      }
      $entityFieldManager = \Drupal::service('entity_field.manager');
      foreach ($entityFieldManager->getFieldDefinitions($options, $bundle) as $field_name => $field_definition) {
        if (!empty($field_definition->getTargetBundle())) {
          //$bundleFields[$field_name]['type'] = $field_definition->getType();
          $bundleFields[$field_name] = $field_definition->getLabel();
        }
      }
      if($options == 'node'){
        $bundleFields['title'] = 'Title';
        $bundleFields['status'] = 'Published';
      }

      return  $none + $bundleFields;
    }
  }


  /**
   * @param $element_type
   *
   * @return array|string[]
   */
  function addOptionToSelect($element_type) {
    //add options entitty
    if ($selectedValue = $element_type) {
      if($selectedValue == 'node'){
        $types = [];
        $contentTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
        foreach ($contentTypes as $contentType) {
          $types[$contentType->id()] = $contentType->label();
        }

      }elseif ($selectedValue == 'user'){
        $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
        $types = [];
        foreach ($roles as $rol) {
          $types[$rol->id()] = $rol->label();
        }

      }elseif ($selectedValue == 'taxonomy_term'){
        $vocabularies = Vocabulary::loadMultiple();
        $types = [];
        foreach ($vocabularies as $voc) {
          $types[$voc->id()] = $voc->label();
        }
      }

      $none = ['none' => 'Select an option'];
      $opt = $none + $types;

    }else{
      $opt = ['none' => 'Select an option'];
    }
    return $opt;
  }
}
