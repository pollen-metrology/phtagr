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

/*
*
* Manages TIFF images. ExifTool can extract metadata from some of these
*
*/
class TiffImageFilterComponent extends BaseFilterComponent {
  var $controller = null;
  var $components = array('Command', 'FileManager', 'SidecarFilter', 'Exiftool');

  public function getName() {
    return "TiffImage";
  }

  public function getExtensions() {
    return array(
        'tiff' => array('hasMetaData' => true),
        'tif' => array('hasMetaData' => true)
        );
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

    if ($this->Exiftool->isEnabled()) {
      $meta = $this->Exiftool->readMetaData($filename);
      $name = basename($filename);
      if ($name != $meta['FileName']) {
        $this->log(">>> File $name differs from {$meta['FileName']}", 'import');
      }
    } else {
      $meta = $this->_readMetaDataGetId3($filename);
    }

    if ($meta === false || count($meta) == 0) {
      $this->FilterManager->addError($filename, 'NoMetaDataFound');
      return false;
    }

    $isNew = false;
    if (!$media) {
      $media = $this->Media->create(array(
        'type' => MEDIA_TYPE_IMAGE,
        ), true);
      if ($this->controller->getUserId() != $file['File']['user_id']) {
        $user = $this->Media->User->findById($file['File']['user_id']);
      } else {
        $user = $this->controller->getUser();
      }
      $media = $this->controller->Media->addDefaultAcl($media, $user);

      $isNew = true;
    };

    if ($this->Exiftool->isEnabled()) {
      $this->Exiftool->extractImageData($media, $meta);
    } else {
      $this->_extractImageDataGetId3($media, $meta);
    }
    
    // condition file parsing  
    $this->_readConditionFile($filename, $media);

    if ($media['Media']['name'] != basename($filename)) {
        $this->log(">>> File ". basename($filename) . " differs from {$media['Media']['name']} (extractImageData)", 'import');
    }
    // fallback for image size
    if (!isset($media['Media']['width']) || $media['Media']['width'] == 0 ||
      !isset($media['Media']['height']) || $media['Media']['height'] == 0) {
      $size = getimagesize($filename);
      if ($size) {
        $media['Media']['width'] = $size[0];
        $media['Media']['height'] = $size[1];
      } else {
        CakeLog::warning("Could not determine image size of $filename");
      }
    }
    if ($options['noSave']) {
      return $media;
    } elseif (!$this->Media->save($media)) {
      CakeLog::error("Could not save Media");
      CakeLog::debug($media);
      $this->FilterManager->addError($filename, 'MediaSaveError');
      return false;
    }
    if ($isNew) {
      $mediaId = $this->Media->getLastInsertID();
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
   * Extracts the date of the file. It extracts the date of IPTC and EXIF.
   * IPTC has the priority.
   *
   * @param data Meta data
   * @return string Date of the meta data or now if not data information was found
   */
  private function _extractMediaDateGetId3($data) {
    // IPTC date
    $dateIptc = $this->_extract($data, 'iptc/IPTCApplication/DateCreated/0', null);
    if ($dateIptc) {
      $dateIptc = substr($dateIptc, 0, 4).'-'.substr($dateIptc, 4, 2).'-'.substr($dateIptc, 6);
      $time = $this->_extract($data, 'iptc/IPTCApplication/TimeCreated/0', null);
      if ($time) {
        $time = substr($time, 0, 2).':'.substr($time, 2, 2).':'.substr($time, 4);
        $dateIptc .= ' '.$time;
      } else {
        $dateIptc .= ' 00:00:00';
      }
      return $dateIptc;
    }
    // No IPTC date: Extract Exif date or now
    return $this->_extract($data, 'jpg/exif/EXIF/DateTimeOriginal', date('Y-m-d H:i:s', time()));
  }


  /**
  *
  * Read Hitachi condition file, if any, and add its content to image metadata
  *
  */    
  private function _readConditionFile($filename, &$media)
  {
        CakeLog::debug("Search condition file for : " . $filename);
        $path_parts = pathinfo($filename);
        $conditionFileFolder = $path_parts['dirname'] . '/' . $path_parts['basename'] . "_cnd";
        if(is_dir($conditionFileFolder))
        {    
            CakeLog::debug("Condition file folder found");
            $condTextConditionFile = $conditionFileFolder . "/" . "cond.txt";
            if(is_file($condTextConditionFile))
            {
                    CakeLog::debug("'cond.txt' Condition file found");
                    $fh = fopen($condTextConditionFile,'r');
                    while ($line = fgets($fh)) 
                    {
                            $needle = '#';
                            if(substr($line, 0, 1) != $needle)
                            {
                                $tokens = preg_split("/[\s]+/", $line);
                                if(count($tokens) >= 2)
                                {
                                    CakeLog::debug("Adding metadata '" . $tokens[0] . "' => '". $tokens[1]. "'");
                                    $media['Media'][$tokens[0]] = $tokens[1];
                                    $media['Field'][$tokens[0]] = $tokens[1];
                                }
                                else
                                {
                                    CakeLog::debug("Ignore metadata line '$line'");
                                }
                            }
                    }
                    fclose($fh);
            }
            else
            {
                    CakeLog::debug("Condition file not found");
            }
        }
        else
        {
            CakeLog::debug("No condition file folder");
        }
        return $media;
  }

  /**
   * Extract the image data from the exif tool array and save it as Media
   *
   * @param data Data array from exif tool array
   * @return Array of the the image data array as image model data
   */
  private function _extractImageDataGetId3(&$media, &$data) {
    $user = $this->controller->getUser();

    $v =& $media['Media'];

    // Media information
    $v['name'] = $this->_extract($data, 'iptc/IPTCApplication/ObjectName/0', $this->_extract($data, 'filename'));
    // TODO Read IPTC date, than EXIF date
    $v['date'] = $this->_extractMediaDateGetId3($data);
    $v['width'] = $this->_extract($data, 'jpg/exif/COMPUTED/Width', 0);
    $v['height'] = $this->_extract($data, 'jpg/exif/COMPUTED/Height', 0);
    $v['duration'] = -1;
    $v['orientation'] = $this->_extract($data, 'jpg/exif/IFD0/Orientation', 1);

    $v['model'] = $this->_extract($data, 'jpg/exif/IFD0/Model', null);
    $v['iso'] = $this->_extract($data, 'jpg/exif/EXIF/ISOSpeedRatings', null);
    
    //CakeLog::debug($data);

    // Associations to meta data: Tags, Categories, Locations
    foreach ($this->Exiftool->fieldMap as $field => $name) {
      $isList = $this->Media->Field->isListField($field);
      $value = $this->_extract($data, "iptc/IPTCApplication/$name", array());
      if (!$value) {
        continue;
      }
      if (!$isList && is_array($value)) {
        $media['Field'][$field] = $value[0];
      } else {
        $media['Field'][$field] = $value;
      }
    }
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
    if (!$this->Exiftool->isEnabled()) {
      CakeLog::error("Exiftool is not defined. Abored writing of meta data");
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

    $data = $this->Exiftool->readMetaData($filename);
    if ($data === false) {
      CakeLog::warning("File has no metadata!");
      return false;
    }

    $args = $this->Exiftool->createExportArguments($data, $media, $filename);
    if (!count($args)) {
      CakeLog::debug("File '$filename' has no metadata changes");
      if (!$this->Media->deleteFlag($media, MEDIA_FLAG_DIRTY)) {
        CakeLog::warning("Could not update image data of media {$media['Media']['id']}");
      }
      return true;
    }

    $result = $this->Exiftool->writeMetaData($filename, $args);
    if ($result !== true) {
      CakeLog::warning("Could not write meta data. Result is " . join(", ", (array) $result));
      return false;
    }

    $this->controller->MyFile->update($file);
    if (!$this->Media->deleteFlag($media, MEDIA_FLAG_DIRTY)) {
      $this->controller->warn("Could not update image data of media {$media['Media']['id']}");
    }
    return true;
  }

  /**
   * Search for an given hash values by a key. If the key does not exists,
   * return the default value
   *
   * @param data Hash array
   * @param key Path or key of the hash value
   * @param default Default Value which will be return, if the key does not
   *        exists. Default value is null.
   * @return mixed The hash value or the default value, id hash key is not set
   */
  private function _extract(&$data, $key, $default = null) {
    $paths = explode('/', trim($key, '/'));
    $result = $data;
    foreach ($paths as $p) {
      if (!isset($result[$p])) {
        return $default;
      }
      $result =& $result[$p];
    }
    return $result;
  }

  private function _readMetaDataGetId3($filename) {
    App::import('vendor', 'getid3/getid3');
    $getId3 = new getId3();
    // disable not required modules
    $getId3->option_tag_id3v1 = false;
    $getId3->option_tag_id3v2 = false;
    $getId3->option_tag_lyrics3 = false;
    $getId3->option_tag_apetag = false;

    $data = $getId3->analyze($filename);
    if (isset($data['error'])) {
      $this->FilterManager->addError($filename, 'VendorError', '', $data['error']);
      CakeLog::error("GetId3 analyzing error: {$data['error'][0]}");
      CakeLog::debug($data);
      return false;
    }
    return $data;
  }

  /*
exiftool -S -n IMG_0498.jpg|sed -e 's/^/  [/' -e 's/: /] => "/' -e 's/$/"/'

array
(
  [ExifToolVersion] => "6.45"
  [FileName] => "IMG_0498.jpg"
  [FileSize] => "1156509"
  [FileModifyDate] => "2007:12:18 08:46:00"
  [FileType] => "JPEG"
  [MIMEType] => "image/jpeg"
  [Make] => "Canon"
  [Model] => "Canon DIGITAL IXUS 40"
  [Orientation] => "1"
  [XResolution] => "180"
  [YResolution] => "180"
  [ResolutionUnit] => "2"
  [ModifyDate] => "2005:06:23 21:03:48"
  [YCbCrPositioning] => "1"
  [ExposureTime] => "0.01666667"
  [FNumber] => "2.8"
  [ExifVersion] => "0220"
  [DateTimeOriginal] => "2005:06:23 21:03:48"
  [CreateDate] => "2005:06:23 21:03:48"
  [ComponentsConfiguration] => "..."
  [CompressedBitsPerPixel] => "3"
  [ShutterSpeedValue] => "0.0166740687605754"
  [ApertureValue] => "2.79795934507662"
  [MaxApertureValue] => "2.79795934507662"
  [Flash] => "25"
  [FocalLength] => "5.8"
  [MacroMode] => "2"
  [Self-timer] => "0"
  [Quality] => "3"
  [CanonFlashMode] => "1"
  [ContinuousDrive] => "0"
  [FocusMode] => "4"
  [CanonImageSize] => "0"
  [EasyMode] => "0"
  [DigitalZoom] => "0"
  [Contrast] => "0"
  [Saturation] => "0"
  [Sharpness] => "0"
  [CameraISO] => "Auto"
  [MeteringMode] => "3"
  [FocusRange] => "1"
  [AFPoint] => "16385"
  [CanonExposureMode] => "0"
  [LensType] => "-1"
  [LongFocal] => "17400"
  [ShortFocal] => "5800"
  [FocalUnits] => "1000"
  [MaxAperture] => "2.79795934507662"
  [MinAperture] => "5.59591869015324"
  [FlashBits] => "8200"
  [FocusContinuous] => "0"
  [AESetting] => "0"
  [ZoomSourceWidth] => "2272"
  [ZoomTargetWidth] => "2272"
  [PhotoEffect] => "0"
  [FocalType] => "2"
  [ScaledFocalLength] => "5800"
  [AutoISO] => "282.842712474619"
  [BaseISO] => "50"
  [MeasuredEV] => "-3.65625"
  [TargetAperture] => "2.79795934507662"
  [TargetExposureTime] => "0.0166740687605754"
  [ExposureCompensation] => "0"
  [WhiteBalance] => "0"
  [SlowShutter] => "0"
  [SequenceNumber] => "0"
  [FlashGuideNumber] => "6.25"
  [FlashExposureComp] => "0"
  [AutoExposureBracketing] => "0"
  [AEBBracketValue] => "0"
  [FocusDistanceUpper] => "2.84"
  [FocusDistanceLower] => "0"
  [BulbDuration] => "0"
  [CameraType] => "250"
  [AutoRotate] => "0"
  [NDFilter] => "0"
  [Self-timer2] => "0"
  [NumAFPoints] => "9"
  [CanonImageWidth] => "2272"
  [CanonImageHeight] => "1704"
  [CanonImageWidthAsShot] => "2272"
  [CanonImageHeightAsShot] => "284"
  [AFPointsUsed] => "0"
  [CanonImageType] => "IMG:DIGITAL IXUS 40 JPEG"
  [CanonFirmwareVersion] => "Firmware Version 1.00"
  [FileNumber] => "1040498"
  [OwnerName] => ""
  [CanonModelID] => "22282240"
  [UserComment] => ""
  [FlashpixVersion] => "0100"
  [ColorSpace] => "1"
  [ExifImageWidth] => "2272"
  [ExifImageLength] => "1704"
  [InteropIndex] => "R98"
  [InteropVersion] => "0100"
  [RelatedImageWidth] => "2272"
  [RelatedImageLength] => "1704"
  [FocalPlaneXResolution] => "10142.86"
  [FocalPlaneYResolution] => "10142.86"
  [FocalPlaneResolutionUnit] => "25.4"
  [SensingMethod] => "2"
  [FileSource] => "3"
  [CustomRendered] => "0"
  [ExposureMode] => "0"
  [DigitalZoomRatio] => "1"
  [SceneCaptureType] => "0"
  [Compression] => "6"
  [ThumbnailOffset] => "5120"
  [ThumbnailLength] => "4289"
  [ImageWidth] => "2272"
  [ImageHeight] => "1704"
  [Keywords] => "night, sebastian"
  [City] => "heidelberg"
  [Sub-location] => "neckarwiese"
  [Country-PrimaryLocationName] => "germany"
  [SupplementalCategories] => "party, pc"
  [Province-State] => "bw"
  [Aperture] => "2.8"
  [ConditionalFEC] => "0"
  [DriveMode] => "2"
  [FlashOn] => "1"
  [FlashType] => "0"
  [ISO] => "141.421356237309"
  [ImageSize] => "2272x1704"
  [Lens] => "5.8"
  [RedEyeReduction] => "0"
  [ScaleFactor35efl] => "6.08360904012188"
  [ShootingMode] => "10"
  [ShutterCurtainHack] => "0"
  [ShutterSpeed] => "0.01666667"
  [ThumbnailImage] => "(Binary data 4289 bytes, use -b option to extract)"
  [CircleOfConfusion] => "0.00493888749765297"
  [FocalLength35efl] => "35.2849324327069"
  [HyperfocalDistance] => "2.43258946878119"
  [LV] => "8.38204879878595"
  [Lens35efl] => "35.2849324327069"
)
*/
}
