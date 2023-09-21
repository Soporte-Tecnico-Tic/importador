<?php

namespace Drupal\content_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;


class BatchController extends ControllerBase {

  /**
   * @param $filename
   * @param $delimiter
   *
   * @return array|false
   */
  public static function csvtoarray_validate($filename, $delimiter = ';') {

    if (!file_exists($filename) || !is_readable($filename)) {
      return FALSE;
    }
    $header = [];
    if (($handle = fopen($filename, 'r')) !== FALSE) {
      while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        if (empty($header)) {
          $header = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $row);
        }else{
          $data[] = array_combine($header, $row);
          }
        }
      }
      fclose($handle);
      return $data;
  }
  public static function csvtoarray_validate_getheader($filename, $delimiter = ';') {

    /* Load the object of the file by it's fid */


    if (!file_exists($filename) || !is_readable($filename)) {
      return FALSE;
    }
    $row = [];
    $header = [];
    if (($handle = fopen($filename, 'r')) !== FALSE) {
      while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        if (empty($header)) {
          $row[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $row[0]);
          $header = array_combine($row,$row);
          return ['none' => 'none'] + $header;
        }
      }
    }
    return false;
  }
  /**
   * @param $data
   * @param $fields
   * @param $type
   * @param $context
   */
  static function process_files($data, $fields,$bundle,$type, &$context) {

    $context['message'] = 'Loading ';
    if($type == 'node'){

      $value_exist = '';
      foreach ($fields as $field) {
        if($field['id'] == 'title'){
          $value_exist = $field['value'];
        }
      }

      if($data[$value_exist]){
        $node_exists = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => $data[$value_exist]]);
        $node_exist = reset($node_exists);
      }else{
      \Drupal::messenger()->addError('el campo TITLE es obligatorio para crear un NODO');
    }
      if(empty($node_exist) && $data[$value_exist]){
        try {
          $node = \Drupal\node\Entity\Node::create(['type' => $bundle]);
          $entityFieldManager = \Drupal::service('entity_field.manager');
          $fields_node = $entityFieldManager->getFieldDefinitions($type, $bundle);

          foreach ($fields as $field) {
            $reference = $fields_node[$field['id']];
            if($reference->getType() == 'entity_reference'){

              //taxonomy
              if($reference->getSettings()['target_type'] == 'taxonomy_term'){
                if(!empty($data[$field['value']])){
                  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $data[$field['value']]]);
                  $term = reset($terms);
                  if(!empty($term)){
                    $node->set($field['id'], $term->id());
                  }else{
                    if($field['value']){
                      $term = Term::create([
                        'name' => $data[$field['value']],
                        'vid' => reset($reference->getSettings()['handler_settings']['target_bundles']),
                      ]);
                        $term->enforceIsNew()
                        ->save();
                      $node->set($field['id'], $term->id());
                    }
                  }
                }

              }
              //node
              if($reference->getSettings()['target_type'] == 'node'){
                if(!empty($data[$field['value']])){
                  $terms = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => $data[$field['value']]]);
                  $term = reset($terms);
                  if(!empty($term)){
                    $node->set($field['id'], $term->id());
                  }
                }

              }
            }else{
              if($reference->getType() == 'datetime'){
                if(!empty($data[$field['value']])){
                  $date = explode('/',$data[$field['value']]);
                  if(sizeof($date)>2){
                    $node->set($field['id'],$date[2].'-'.$date[1].'-'.$date[0].'T12:00:00' );
                  }
                }
              }else{
                if($field['value']){
                  if(!empty($data[$field['value']])){
                    $node->set($field['id'], $data[$field['value']]);
                  }
                }
              }
            }
          }
          $node->enforceIsNew();
          $node->save();
          $_SESSION['data_saved'] ? $_SESSION['data_saved'] = $_SESSION['data_saved'] + 1 : $_SESSION['data_saved'] = 1;
        } catch (\Exception $ex) {
          \Drupal::logger('import content')->error($ex->getMessage());
        }
      }

    }
    if($type == 'taxonomy_term'){

      $value_exist = '';
      foreach ($fields as $field) {
        if($field['id'] == 'name'){
          $value_exist = $field['value'];
        }
      }

      if($data[$value_exist]){
        $tax_exists = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $data[$value_exist]]);
        $tax_exist = reset($tax_exists);
      }else{
        \Drupal::messenger()->addError('el campo NAME es obligatorio para crear un TERMINO');
      }


      if(empty($tax_exist) && $data[$value_exist]){
        try {
          $node = Term::create(['vid' => $bundle]);
          $entityFieldManager = \Drupal::service('entity_field.manager');
          $fields_node = $entityFieldManager->getFieldDefinitions($type, $bundle);

          foreach ($fields as $field) {
            $reference = $fields_node[$field['id']];
            if($reference->getType() == 'entity_reference'){

              //taxonomy
              if($reference->getSettings()['target_type'] == 'taxonomy_term'){
                if(!empty($data[$field['value']])){
                  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $data[$field['value']]]);
                  $term = reset($terms);
                  if(!empty($term)){
                    $node->set($field['id'], $term->id());
                  }else{
                    if($field['value']){
                      $term = Term::create([
                        'name' => $data[$field['value']],
                        'vid' => reset($reference->getSettings()['handler_settings']['target_bundles']),
                      ]);
                      $term->enforceIsNew()
                        ->save();
                      $node->set($field['id'], $term->id());
                    }
                  }
                }

              }
              //node
              if($reference->getSettings()['target_type'] == 'node'){
                if(!empty($data[$field['value']])){
                  $terms = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => $data[$field['value']]]);
                  $term = reset($terms);
                  if(!empty($term)){
                    $node->set($field['id'], $term->id());
                  }
                }

              }
            }else{
              if($reference->getType() == 'datetime'){
                if(!empty($data[$field['value']])){
                  $date = explode('/',$data[$field['value']]);
                  if(sizeof($date)>2){
                    $node->set($field['id'],$date[2].'-'.$date[1].'-'.$date[0].'T12:00:00' );
                  }
                }
              }else{
                if($field['value']){
                  if(!empty($data[$field['value']])){
                    $node->set($field['id'], $data[$field['value']]);
                  }
                }
              }
            }
          }
          $node->enforceIsNew();
          $node->save();
          $_SESSION['data_saved'] ? $_SESSION['data_saved'] = $_SESSION['data_saved'] + 1 : $_SESSION['data_saved'] = 1;
        } catch (\Exception $ex) {
          \Drupal::logger('import content')->error($ex->getMessage());
        }
      }

    }
  }

  /**
   * Batch 'finished' callback
   */
  static function process_item_file_data_finish($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      \Drupal::database()->update('content_import_list')
        ->fields(['data_import' =>  $_SESSION['data_saved'], 'last_import' => time()])
        ->condition('id', $_SESSION['id_current_importer'] )
        ->execute();
      $message = \Drupal::translation()->formatPlural(
        $_SESSION['data_saved'],
        'Una entidad almacenada', '@count Entidades almacenadas'
      );
      \Drupal::messenger()->addMessage($message);
    }
    else {
      $message = t('Finished with an error.');
      \Drupal::messenger()->addMessage($message);
    }

  }
}
