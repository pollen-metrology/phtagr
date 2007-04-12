<?php

include_once("$phtagr_lib/SectionBase.php");

class SectionBulb extends SectionBase
{

var $tags;
var $sets;
var $locations;

function SectionBulb()
{
  $this->SectionBase("bulb");
  $this->tags=array();
  $this->sets=array();
  $this->locations=array();
}

function set_data($tags, $sets, $locations)
{
  $this->tags=$tags;
  $this->sets=$sets;
  $this->locations=$locations;
}

function print_content()
{
  global $user;

  $search=new Search();
  $search->from_url();
  $userid=$search->get_userid();
  $src=$user->get_theme_dir().'/globe.png';
  $img="<img src=\"$src\" border=\"0\" alt=\"@\" title=\""._("Search globaly")."\"/>";
  echo "<h2>"._("Summarize")."</h2>\n";

  // Get current search and reset positions
  $add_url=clone $search;
  $add_url->set_page_num(0);
  $add_url->set_pos(0);

  $url=new Search();
  $url->add_param('section', 'explorer');
  if (count($this->tags)>0)
  {
    echo "\n<h3>"._("Tags:")."</h3>\n<ul>";
    arsort($this->tags);
    foreach ($this->tags as $tag => $nums) 
    {
      echo "<li>";
      $url->add_tag($tag);
      // Add global search
      if ($userid>0) 
      {
        $url->set_userid(0);
        echo "<a href=\"".$url->get_url()."\">$img</a> ";
        $url->set_userid($userid);
      }
      if (!$add_url->has_tag($tag))
      {
        $add_url->add_tag($tag);
        echo "<a href=\"".$add_url->get_url()."\">+</a>/";
        $add_url->del_tag($tag);
        $add_url->add_tag("-".$tag);
        echo "<a href=\"".$add_url->get_url()."\">-</a> ";
        $add_url->del_tag("-".$tag);
      } else {
        echo "+/- ";
      }
      echo "<a href=\"".$url->get_url()."\">".$this->escape_html($tag)."</a>";

      if ($nums>1)
        echo " <span class=\"hits\">($nums)</span>";
      echo "</li>\n";
      $url->del_tag($tag);
    }
    echo "</ul>\n";
  }

  if (count($this->sets)>0)
  {
    echo "\n<h3>"._("Sets:")."</h3>\n<ul>";
    arsort($this->sets);
    foreach ($this->sets as $set => $nums) 
    {
      echo "<li>";
      $url->add_set($set);
      // Add global search
      if ($userid>0) 
      {
        $url->set_userid(0);
        echo "<a href=\"".$url->get_url()."\">$img</a> ";
        $url->set_userid($userid);
      }
      if (!$add_url->has_set($set))
      {
        $add_url->add_set($set);
        echo "<a href=\"".$add_url->get_url()."\">+</a>/";
        $add_url->del_set($set);
        $add_url->add_set("-".$set);
        echo "<a href=\"".$add_url->get_url()."\">-</a> ";
        $add_url->del_set("-".$set);
      } else {
        echo "+/- ";
      }
      echo "<a href=\"".$url->get_url()."\">".$this->escape_html($set)."</a>";
      if ($nums>1)
        echo " <span class=\"hits\">($nums)</span>";
      echo "</li>\n";
      $url->del_set($set);
    }
    echo "</ul>\n";
  }

  if (count($this->locations)>0)
  {
    echo "\n<h3>"._("Locations:")."</h3>\n<ul>";
    arsort($this->locations);
    foreach ($this->locations as $loc => $nums) 
    {
      echo "<li>";
      $url->set_location($loc);
      if ($userid>0) 
      {
        $url->set_userid(0);
        echo "<a href=\"".$url->get_url()."\">$img</a> ";
        $url->set_userid($userid);
      }
      if (!$add_url->has_location($loc))
      {
        $loc_old=$add_url->get_location();
        $add_url->set_location($loc);
        echo "<a href=\"".$add_url->get_url()."\">+</a> ";
        $add_url->set_location($loc_old);
      } else {
        echo "+ ";
      }
      echo "<a href=\"".$url->get_url()."\">".$this->escape_html($loc)."</a>";
      if ($nums>1)
        echo " <span class=\"hits\">($nums)</span>";
      echo "</li>\n";
      $url->del_location($loc);
    }
    echo "</ul>\n";
  }

  echo "<h3>"._("Sort by:")."</h3>\n<ul>\n";
  $order=array('date' => _("Date"), 
              '-date' => _("Date desc"),
              'popularity' => _("Popularity"),
              'voting' => _("Voting"),
              'newest' => _("Newest"),
              'changes' => _("Changes"));
  foreach ($order as $key => $text) {
    $url->set_orderby($key);
    $add_url->set_orderby($key);
    echo "  <li>";
    // Add global search
    if ($userid>0) 
    {
      $url->set_userid(0);
      echo "<a href=\"".$url->get_url()."\">$img</a> ";
      $url->set_userid($userid);
    }
    echo "<a href=\"".$add_url->get_url()."\">+</a> ";
    echo "<a href=\"".$url->get_url()."\">$text</a></li>\n";
    $url->del_orderby();
    $add_url->del_orderby();
  }
  echo "</ul>\n";
}

}
?>