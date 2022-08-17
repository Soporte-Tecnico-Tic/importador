<?php

namespace Drupal\content_import\Controller;

use Drupal\Core\Controller\ControllerBase;


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
  public function csvtoarray_validate_getheader($filename, $delimiter = ';') {

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
  static function process_files($data, $fields,$type, &$context) {

    $context['message'] = 'Loading ';
    try {
      $node = \Drupal\node\Entity\Node::create(['type' => $type]);
      foreach ($fields as $field) {
        $node->set($field['id'], $data[$field['value']]);
      }
      $node->enforceIsNew();
      $node->save();
      $_SESSION['data_saved'] ? $_SESSION['data_saved'] = $_SESSION['data_saved'] + 1 : $_SESSION['data_saved'] = 1;
    } catch (\Exception $ex) {
      \Drupal::logger('import content')->error($ex->getMessage());
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
