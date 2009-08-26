<?php
/*
 * phtagr.
 * 
 * Multi-user image gallery.
 * 
 * Copyright (C) 2006-2009 Sebastian Felis, sebastian@phtagr.org
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2 of the 
 * License.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
    
App::import('Core', 'Sanitize');

class ImageDataHelper extends AppHelper {

  var $helpers = array('Ajax', 'Html', 'Form', 'Search');

  var $Sanitize = null;

  function beforeRender() {
    $this->Search->initialize();
    $this->Sanitize =& new Sanitize();
  }

  /** Returns the image size of the given media. This function considers the
    orientaion of the media.
    @param media Media model data
    @param size New size. Numeric value of maximum width or height. Possible
    text values: 'mini', 'thumb', 'preview', 'original'. If false than it
    returns the media size
    @param square Returns the squared size of resize value 
    @return array of sizes. array(height, width, html size) */
  function getimagesize($media, $size = false, $square=false) {
    if (!isset($media['Media']['width']) ||
      !isset($media['Media']['height']) ||
      !isset($media['Media']['orientation'])) {
      return array(0 => 0, 1 => 0, 3 => '');
    }

    if ($size && !is_numeric($size) && !in_array($size, array('mini', 'thumb', 'preview', 'original'))) {
      Logger::err("Wrong media size $resize");
    }

    $resize = false;
    switch ($size) {
      case 'mini':
        $resize = OUTPUT_SIZE_MINI;
        $square = true;
        break;
      case 'thumb':
        $resize = OUTPUT_SIZE_THUMB;
        break;
      case 'preview':
        $resize = OUTPUT_SIZE_PREVIEW;
        break;
      case 'original':
        $resize = false;
        break;
      default:
        if ($size && is_numeric($size)) {
          $resize = $size;
        }
    }

    $width = $media['Media']['width'];
    $height = $media['Media']['height'];
    if ($resize) {
      if ($square) {
        $width = $resize;
        $height = $resize;
      } else {
        if ($width > $resize && $width >= $height) {
          $height = intval($resize * ($height / $width));
          $width = $resize;
        } elseif ($height > $resize && $height > $width) {
          $width = intval($resize * ($width / $height));
          $height = $resize;
        }
      }
    }
    $result = array();

    // Rotate the image according to the orientation
    $orientation = $media['Media']['orientation'];
    if ($orientation >= 5 && $orientation <= 8) {
      $result[0] = $height;
      $result[1] = $width;
    } else {
      $result[0] = $width;
      $result[1] = $height;
    }

    $result[3] = "height=\"{$result[1]}\" width=\"{$result[0]}\"";

    return $result;
  }
  
  /** Creates an media image with the link
    @param media Media model data
    @param options Media size or option array. Options are
      type - Type of the media. String values: 'mini', 'thumb', 'preview', 'original'
      size - Size of the media - optional.
      param - Extra url parameter 
      div - Wrapped div arround the link with the class given in the div options
    @return HTML link with media image */
  function mediaLink($media, $options = array()) {
    if (!isset($media['Media']['id'])) {
      Logger::err("Media id is not set");
      return false;
    }
    if (!is_array($options)) {
      $options = array('type' => $options);
    }
    $options = am(array('type' => 'thumb', 'params' => false, 'size' => false, 'div' => false), $options);

    if (!in_array($options['type'], array('mini', 'thumb', 'preview', 'original'))) {
      Logger::err("Wrong media type {$options['type']}");
      return false;
    }
    if (!$options['size']) {
      $options['size'] = $options['type'];
    }

    $imgSrc = Router::url("/media/{$options['type']}/{$media['Media']['id']}");
    $size = $this->getimagesize($media, $options['size']);
    $alt = $this->Sanitize->html($media['Media']['name'], true);
    $img = "<img src=\"$imgSrc\" {$size[3]} alt=\"$alt\" title=\"$alt\" />";
    $link = "/images/view/{$media['Media']['id']}";
    if ($options['params']) {
      $link .= $options['params'];
    }

    $out = $this->Html->link($img, $link, false, false, false);
    if ($options['div']) {
      $out = '<div class="'.$options['div'].'">'.$out.'</div>';
    }
    return $out;
  }

  /** Returns a link of the media date with different options
    @param media Media model data
    @param option
      - from: All media after given media
      - to: All media before given media
      - offset: given in hours (3h), days (3.5d) or months (6m) 
    @return Link of the date search */
  function getDateLink(&$media, $option = false) {
    $date = $media['Media']['date'];
    if ($option == 'from') {
      return $this->Search->getUri(array('from' => $date, 'sort' => '-date'));
    } elseif ($option == 'to') {
      return $this->Search->getUri(array('to' => $date, 'sort' => 'date'));
    } elseif (preg_match('/^(\d+(.\d+)?)([hdm])$/', $option, $matches)) {
      $offset = $matches[1].$matches[2];
      switch ($matches[3]) {
        case 'h':
          $offset *= 60*60;
          break;
        case 'd':
          $offset *= 24*60*60;
          break;
        case 'm':
          $offset *= 30*24*60*60;
          break;
        default:
          Logger::err("Unknown date offset {$matches[3]}");
      }
    } else {
      $offset = (integer)$option;
    }

    $from = date('Y-m-d H:i:s', strtotime($date) - $offset);
    $to = date('Y-m-d H:i:s', strtotime($date) + $offset);
    return $this->Search->getUri(array('from' => $from, 'to' => $to));
  }

  /** Returns an single icon of a acl */
  function _acl2icon($acl, $titlePrefix = '') {
    $t='';
    // Write access
    if (($acl & ACL_WRITE_MASK) == ACL_WRITE_META) {
      $t .= $this->Html->image('icons/tag.png', array('alt' => 'm', 'title' => $titlePrefix.'edit the meta data'));
    } elseif (($acl & ACL_WRITE_MASK) == ACL_WRITE_TAG) {
      $t .= $this->Html->image('icons/tag_blue.png', array('alt' => 't', 'title' => $titlePrefix.'edit the tags'));
    }

    // Read access
    if (($acl & ACL_READ_MASK) == ACL_READ_ORIGINAL) {
      $t .= $this->Html->image('icons/disk.png', array('alt' => 'o', 'title' => $titlePrefix.'download this media'));
    } elseif (($acl & ACL_READ_MASK) == ACL_READ_PREVIEW) {
      $t .= $this->Html->image('icons/picture.png', array('alt' => 'v', 'title' => $titlePrefix.'view this media'));
    }
    if ($t == '') {
      $t='-';
    }
    return $t;
  }

  /** Returns an text repesentation of the acl */
  function _acl2text($data) {
    //$output = $this->Html->image('icons/user.png', array('alt' => 'groups', 'title' => "Access for group members")).': ';
    $output = '<span title="Access for group members">group</span>';
    if (isset($data['Group']['name'])) {
      $name = $data['Group']['name'];
      $output .= ' ('.$this->Html->link($name, $this->Search->getUri(array(), array('group' => $data['Group']['name'])), array('title' => "This media belongs to the group '$name'")).')';
    }
    $output .= ': ';
    $output .= $this->_acl2icon($data['Media']['gacl'], 'Group members can ').' ';

    //$output .= $this->Html->image('icons/group.png', array('alt' => 'users', 'title' => "Access for users")).': ';
    $output .= '<span title="Access for users">users: </span> ';
    $output .= $this->_acl2icon($data['Media']['uacl'], 'Users can ').' ';

    //$output .= $this->Html->image('icons/world.png', array('alt' => 'public', 'title' => "Public access")).': ';
    $output .= '<span title="Public access">public: </span> ';
    $output .= $this->_acl2icon($data['Media']['oacl'], 'The public can ');
    return $output;
  }

  function _metaDate($data) {
    $id = "date-".$data['Media']['id'];
    $output = '<span onmouseover="toggleVisibility(\''.$id.'\', \'inline\');"';
    $output .= ' onmouseout="toggleVisibility(\''.$id.'\', \'inline\');">';

    $output .= $this->Html->link($data['Media']['date'], $this->getDateLink(&$data, '3h'));
    $output .= ' ';

    $output .= '<div style="display: none;" class="actionlist" id="'.$id.'">';
    $icon = $this->Html->image('icons/date_previous.png', array('alt' => '<', 'title' => "View media of previous dates"));
    $output .= $this->Html->link($icon, $this->getDateLink(&$data, 'to'), array('escape' => false));

    $icon = $this->Html->image('icons/calendar_view_day.png', array('alt' => 'd', 'title' => "View media of this day"));
    $output .= $this->Html->link($icon, $this->getDateLink(&$data, '12h'), array('escape' => false));

    $icon = $this->Html->image('icons/calendar_view_week.png', array('alt' => 'w', 'title' => "View media of this week"));
    $output .= $this->Html->link($icon, $this->getDateLink(&$data, '3.5d'), array('escape' => false));

    $icon = $this->Html->image('icons/calendar_view_month.png', array('alt' => 'm', 'title' => "View media of this month"));
    $output .= $this->Html->link($icon, $this->getDateLink(&$data, '15d'), array('escape' => false));

    $icon = $this->Html->image('icons/date_next.png', array('alt' => '>', 'title' => "View media of next dates"));
    $output .= $this->Html->link($icon, $this->getDateLink(&$data, 'from'), array('escape' => false));
    $output .= '</div></span>';

    return $output;
  }

  function _metaHabtm($data, $habtm) {
    if (!count($data[$habtm])) {
      return false;
    }

    $base = "/explorer";
    if ($this->action == 'user') {
      $base .= "/user/".$this->params['pass'][0];
    }
    $base .= "/".strtolower($habtm);

    $links = array();
    foreach ($data[$habtm] as $assoc) {
      $links[] = $this->Html->link($assoc['name'], "$base/{$assoc['name']}");
    }
    return implode(', ', $links);
  }

  function _metaAccess($data) {
    $id = $data['Media']['id'];
    $output = '<div class="actionlist">';
    $output .= $this->_acl2text($data);

    $output .= '</div>';

    return $output;
  }

  function metaTable($data, $withMap = false) {
    $cells= array();
    if (!$data) 
      return $cells;

    $mediaId = $data['Media']['id'];

    //Logger::debug($this->Search->_data); 
    //Logger::debug("HUHU");
    $userId = $this->Search->getUser();
    if ($userId) {
      $this->Search->setUser($userId);
    }
    $cells[] = array("Date:", $this->_metaDate(&$data));

    if (count($data['Tag'])) {
      $cells[] = array('Tags:', $this->_metaHabtm(&$data, 'Tag'));
    }
    if (count($data['Category'])) {
      $cells[] = array('Categories:', $this->_metaHabtm(&$data, 'Category'));
    }
    if (count($data['Location'])) {
      $cells[] = array('Locations:', $this->_metaHabtm(&$data, 'Location'));
    }

    if ($data['Media']['isOwner']) {
      $cells[] = array('Access:', $this->_metaAccess($data));
    }
    
    // Action list 
    $output = '';
    if ($data['Media']['canWriteTag']) {
      $output = $this->Form->checkbox('selected][', array('value' => $mediaId, 'id' => 'select-'.$mediaId, 'onclick' => "selectMedia($mediaId);"));
    }

    if ($data['Media']['canWriteTag']) {
      $output .= ' '.$this->Ajax->link(
        $this->Html->image('icons/tag_blue_edit.png', array('alt' => 'Edit tags', 'title' => 'Edit tags')), 
        '/explorer/editmeta/'.$mediaId, 
        array('update' => 'meta-'.$mediaId), null, false);
    }
    if ($data['Media']['canReadOriginal']) {
      foreach ($data['File'] as $file) {
        $output .= ' '.$this->Html->link(
          $this->Html->image('icons/disk.png', 
            array('alt' => $file['file'], 'title' => 'Save file '.$file['file'])), 
          '/media/file/'.$file['id'].'/'.$file['file'], null, null, false);
      }
    }

    if ($withMap && isset($data['Media']['latitude']) && isset($data['Media']['longitude'])) {
      $output .= ' '.$this->Html->link(
          $this->Html->image('icons/map.png',
            array('alt' => 'Show location in a map', 'title' => 'Show location in a map')),
          '#',
          array('onclick' => sprintf('showMap(%d, %f,%f);return false;', $data['Media']['id'], $data['Media']['latitude'],$data['Media']['longitude'])),
          null, false);
    }
    
    if ($data['Media']['isOwner']) {
      $output .= ' '.$this->Ajax->link(
        $this->Html->image('icons/key.png', 
          array('alt' => 'Edit ACL', 'title' => 'Edit access rights')), 
        '/explorer/editacl/'.$mediaId, 
        array('update' => 'meta-'.$mediaId), null, false);
      if ($data['Media']['isDirty'])
        $output .= ' '.$this->Ajax->link(
          $this->Html->image('icons/database_refresh.png', 
            array('alt' => 'Synchronize db with image', 'title' => 'Synchronize meta data with the image')), 
          '/explorer/sync/'.$mediaId, 
          array('update' => 'meta-'.$mediaId), null, false);
    }

    if ($output) {
      $output = "<div class=\"actionlist\">$output</div>\n";
      $cells[] = array("Actions:", $output);
    }

    return $cells;
  }

  function _getCurrentLevel($data, $flag, $mask) {
    $data = am(array('Media' => array('oacl' => 0, 'uacl' => 0, 'gacl' => 0)), $data);
    if (($data['Media']['oacl'] & $mask) >= $flag) {
      return ACL_LEVEL_OTHER;
    }
    if (($data['Media']['uacl'] & $mask) >= $flag) {
      return ACL_LEVEL_USER;
    }
    if (($data['Media']['gacl'] & $mask) >= $flag) {
      return ACL_LEVEL_GROUP;
    }
    return ACL_LEVEL_PRIVATE;
  }

  /**
    Creates a ACL select element
    @param fieldName fieldname of the select
    @param data If data is numeric, the value is handled as acl level. If data
    is an array, the data is assumed to be a image model data array. The level
    is extracted bz the flag and the mask.
    @param flag Bit flag of the acl (used for image data array)
    @param mask Bit mask of the acl (used for image data array)

  // 0=keep, 1=me only, 2=group, 3=user, 4=others
  */
  function acl2select($fieldName, $data, $flag=0, $mask=0, $options=null) {
    if (is_array($data)) {
      $level = $this->_getCurrentLevel(&$data, &$flag, &$mask);
    } elseif (is_numeric($data)) {
      $level = $data;
    } else {
      $level = ACL_LEVEL_PRIVATE;
    }

    // level check
    if ($level < ACL_LEVEL_KEEP|| $level > ACL_LEVEL_OTHER) {
      $level = ACL_LEVEL_PRIVATE;
    }

    //$this->log($data['Media']);
    //$this->log("level=$level, flag=$flag, mask=$mask");
    $acl = array(
      ACL_LEVEL_KEEP => 'Keep',
      ACL_LEVEL_PRIVATE => 'Me only',
      ACL_LEVEL_GROUP => 'Group members',
      ACL_LEVEL_USER => 'Users',
      ACL_LEVEL_OTHER => 'Everyone');
    $options = am($options, array('type' => 'select', 'options' => $acl, 'selected' => $level));
    //$this->log($options);
    return $this->Form->input($fieldName, $options);
  }  

  /** Returns the visibility icon for the own media 
    @params media Media model data 
    @return Html output for the icon or false */
  function getVisibilityIcon(&$media) {
    $icon = false;
    if (isset($media['Media']['isOwner']) && $media['Media']['isOwner']) {
      switch ($media['Media']['visibility']) {
        case ACL_LEVEL_OTHER: 
          $icon = $this->Html->image('icons/world.png', array('title' => 'This media is public visible'));;
          break;
        case ACL_LEVEL_USER: 
          $icon = $this->Html->image('icons/group.png', array('title' => 'This media is visible for users'));;
          break;
        case ACL_LEVEL_GROUP: 
          $icon = $this->Html->image('icons/user.png', array('title' => 'This media is visible for group members'));;
          break;
        default: 
          $icon = $this->Html->image('icons/stop.png', array('title' => 'This media is private'));;
          break;
      }
    }
    return $icon;
  }

}
?>
