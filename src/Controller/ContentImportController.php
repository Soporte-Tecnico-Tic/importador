<?php

namespace Drupal\content_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;

/**
 * Content Import Controller
 */
class ContentImportController extends ControllerBase {


  /**
   * @return array
   */
  public function viewImports(){

    $header = ['id','Nombre', 'Datos', 'Elemento', 'Tipo Elemento', 'Ult. Importación', 'Acciones'];
    $query =  \Drupal::database()->select('content_import_list', 'r')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender');

    $result = $query
      ->fields('r', ['id','name', 'data_import', 'element', 'type_element', 'last_import'])
      ->orderBy('r.id', 'DESC')
      ->execute();

    $rows = [];
    $date_formatter = \Drupal::service('date.formatter');
    foreach ($result as $item) {
      if($item->last_import){
        $item->last_import = $date_formatter->format($item->last_import);
      }else{
        $item->last_import = '';
      }

      $row = (array) $item;
      $row[] = Link::createFromRoute('Editar', 'content_import.edit_entity', ['id' => $item->id])->toString();

      $rows[] = $row;
    }

    $build['table'] = array(
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    );

    $build['pager'] = array(
      '#type' => 'pager',
    );
    return $build;
  }
}

