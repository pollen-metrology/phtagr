<?php
/**
 * PHP versions 5
 *
 * phTagr : Organize, Browse, and Share Your Photos.
 * Copyright 2006-2013, Sebastian Felis (sebastian@phtagr.org)
 *
 * Licensed under The GPL-2.0 License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2006-2013, Sebastian Felis (sebastian@phtagr.org)
 * @link          http://www.phtagr.org phTagr
 * @package       Phtagr
 * @since         phTagr 2.3
 * @license       GPL-2.0 (http://www.opensource.org/licenses/GPL-2.0)
 */


App::uses('BaseFilter', 'Component');

class GenericFilterComponent extends BaseFilterComponent {
  var $controller = null;
  var $components = array('Command', 'FileManager', 'SidecarFilter');

  public function getName() {
    return "Generic";
  }


  /**
  *
  * Define no extension : will be used as fallback for unmanaged files
  * 
  */
  public function getExtensions() {
    return array("GENERIC");
  }

  /**
   * Read the meta data from the file
   *
   * @param file File model data
   * @param media Reference of Media model data
   * @param options Options
   *  - noSave if set dont save model data
   * @return mixed The image data array or False on error
   */
  public function read(&$file, &$media = null, $options = array()) {
    $options = am(array('noSave' => false), $options);
    $filename = $this->MyFile->getFilename($file);
    
    CakeLog::debug("GenericFilterComponent#read() file : " . $file);
    CakeLog::debug("GenericFilterComponent#read() filename : " . $filename);

    $isNew = false;
    if (!$media) {
     CakeLog::debug("GenericFilterComponent#read() creating new media");
      $media = $this->Media->create(array(
        'type' => MEDIA_TYPE_IMAGE,
        ), true);
     CakeLog::debug("GenericFilterComponent#read() new media created ");

      if ($this->controller->getUserId() != $file['File']['user_id']) {
        $user = $this->Media->User->findById($file['File']['user_id']);
      } else {
        $user = $this->controller->getUser();
      }
      $media = $this->controller->Media->addDefaultAcl($media, $user);

      $isNew = true;
    };

    $media['Media']['name'] = basename($filename);
    if (!isset($media['Media']['date'])) {
      $media['Media']['date'] = date('Y-m-d H:i:s', time());
    }

    if ($options['noSave']) {
      return $media;
    } elseif (!$this->controller->Media->save($media)) {
      CakeLog::error("Could not save Media");
      CakeLog::debug($media);
      $this->FilterManager->addError($filename, 'MediaSaveError');
      return false;
    }

    if ($isNew) {
      $mediaId = $this->Media->getLastInsertID();

     CakeLog::debug("GenericFilterComponent#read() new media ID : " . $mediaId);

      if (!$this->controller->MyFile->setMedia($file, $mediaId)) {
        $this->Media->delete($mediaId);
        $this->FilterManager->addError($filename, 'FileSaveError');
        return false;
      } else {
        CakeLog::info("Created new Media (id $mediaId)");
        $media = $this->Media->findById($mediaId);
      }
    } else {
      CakeLog::debug("Updated media (id ".$media['Media']['id'].")");
    }
    $this->controller->MyFile->updateReaded($file);
    $this->controller->MyFile->setFlag($file, FILE_FLAG_DEPENDENT);

    return $media;
  }

  /**
   * Write the meta data to an image file
   *
   * @param file File model data
   * @param media Media model data
   * @param options Array of options
   * @return mixed False on error
   */
  public function write(&$file, &$media, $options = array()) {
    if (!$file || !$media) {
      CakeLog::error("File or media is empty");
      return false;
    }
    $filename = $this->controller->MyFile->getFilename($file);

    if ($this->controller->getOption('xmp.use.sidecar', 0)) {
      if ($this->SidecarFilter->hasSidecar($filename, true)) {
        $filename_xmp = substr($filename, 0, strrpos($filename, '.') + 1) . 'xmp';
        $sidecar = $this->MyFile->findByFilename($filename_xmp);
        return ($this->SidecarFilter->write($sidecar, $media));
      } else {
        return false;
      }
    }

    if (!file_exists($filename) || !is_writeable(dirname($filename)) || !is_writeable($filename)) {
      $id = isset($media['Media']['id']) ? $media['Media']['id'] : 0;
      CakeLog::warning("File: $filename (#$id) does not exists nor is readable");
      return false;
    }
    return false;
  }
}
