<?php if (!defined('PmWiki')) exit();
/*
 * ListCategories - create a list of categories
 * (c) 2006 Stefan Schimanski <sts@1stein.org>
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

$RecipeInfo['ListCategories']['Version'] = '$Rev: 51 $';

SDV($ListCategories_CreatePages, true); // automatically create Category.Bla pages?
SDV($ListCategories_IncludeCategories, "/.*/");
SDV($ListCategories_ExcludeCategories, "/^Blog$/");
SDV($ListCategories_SizedlistNum, 30 ); // number of item in the SizedList format
SDV($ListCategories_SizedlistRandom, false ); // use random tags or the first in the list
SDV($ListCategories_SizedlistMinFontSize, 8 ); // minimal font size
SDV($ListCategories_SizedlistMaxFontSize, 26 ); // maximal font size
SDV($ListCategories_SortSizedList, false ); // sort tags in the tag cloud alphabetically?
SDV($ListCategories_SortSimpleList, false ); // sort tags in the simple list alphabetically?

$ListCategories_MarkupFunctions["simple"] = ListCategories_SimpleList;
$ListCategories_MarkupFunctions["sized"] = ListCategories_SizedList;
$ListCategories_MarkupFunctions["include"] = ListCategories_IncludeList;
$ListCategories_MarkupFunctions["pagelist"] = ListCategories_Pagelink;

// Example: (:listcategories simple (Main|Pics|Blog)\..*:)
Markup('(:listcategories format where:)','directives',"/\\(:listcategories\\s+([A-Za-z0-9]+)\\s+([^:]*):\\)/e","ListCategories('$1','$2')");


/*********************************************************************/

function ListCategories_IncludeList( $categories ) {
  $out = "";
  foreach( $categories as $pn => $count ) {
    $out .= "(:include Category.$pn:)\n";
  }
  return PRR($out);  
}

SDV($LinkCategoryFmt,"<a class='categorylink' rel='tag' href='\$LinkUrl'>\$LinkText</a>");
function ListCategories_SimpleList( $categories ) {
  // sort
  global $ListCategories_SortSimpleList;
  if( $ListCategories_SortSimpleList ) ksort( $categories );

  // output
  $out = "";
  $space = "";
  foreach( $categories as $pn => $count ) {
    $out .= $space . "[[!$pn]]";
    $space = " ";
  }
  return PRR($out);
}

function ListCategories_Pagelink( $categories ) {
  global $pagename;
  $pages = array();
  foreach( $categories as $pn => $count ) {
    $opt = array( "link"=>"Category.$pn", "count"=>"5", "order"=>"-ctime" );
    $matches = array_values(MakePageList( $pagename, $opt, 0 ));
    $pages = array_merge( array_intersect($pages, $matches),
			  array_diff($pages, $matches),
			  array_diff($matches, $pages) );
  }

  if( count($pages)>5 ) $pages = array_slice( $pages, 0, 6 );

  $out = "";
  foreach( $pages as $page ) {
    if( $page!=$pagename ) {
      $time = PageVar($page, '$CreationTime');
      if( ! $time ) PageVar($page, '$LastModified');
      $out .= "[[$page|" . PageVar($page, '$Title') . "]] - " . $time . "\\\\\n";
    }
  }

  return PRR($out);
}

function ListCategories_SizedList( $categories ) {
    global $ListCategories_SizedlistNum, $ListCategories_SizedlistRandom,
        $ListCategories_SizedlistMinFontSize, $ListCategories_SizedlistMaxFontSize;
  $all = count( $categories );
  $sum = 0;
  $num = 0;
  $selected = array();
  foreach( $categories as $pn => $count ) {
    $showThisOne = true;
    if( $ListCategories_SizedlistRandom && rand(0, $all)>$ListCategories_SizedlistNum ) $showThisOne = false;
    
    if( $showThisOne ) {
      $sum += $count;
      $num++;
      $selected[$pn]=$count;
    }

    if( !$ListCategories_SizedlistRandom && $num>$ListCategories_SizedlistNum ) break;
  } 

  // sort them
  global $ListCategories_SortSizedList;
  if( $ListCategories_SortSizedList ) ksort( $selected );

  // output them
  $out = "";
  $space = "";
  $min = $ListCategories_SizedlistMinFontSize;
  $max = $ListCategories_SizedlistMaxFontSize;
  $mult = ($max-$min)/3;
  foreach( $selected as $pn => $count ) {
    $value = $count/($sum/$num);
    if( $value>3 ) $value=3;
#    $out=$out."$space<span style=\"font-size:".intval($value*$mult+$min)."px;font-weight:".intval($value*200+500)."\">[[Category.$pn|$pn]]</span>";
    $out=$out."$space<span style=\"font-size:".intval($value*$mult+$min)."px;\">[[Category.$pn|$pn]]</span>";
    $space = " ";
  }
  return PRR($out);
}

function GetCategoriesCountList( $where ) {
  // get Category links (copied from pagelists.php PageIndexGrep)
  global $PageIndexFile, $ListCategories_CreatePages,
    $ListCategories_IncludeCategories, $ListCategories_ExcludeCategories;
  if (!$PageIndexFile) return array();
  StopWatch('ListCategories begin');
  $pagelist = array();
  $fp = @fopen($PageIndexFile, 'r');
  if ($fp) {
    while (!feof($fp)) {
      $line = fgets($fp, 4096);
      while (substr($line, -1, 1) != "\n" && !feof($fp))
        $line .= fgets($fp, 4096);
      $i = strpos($line, ':');
      if (!$i) continue;

      // look for Category links
      $matches = array();
      if( preg_match("/^$where:/", $line ) ) {
	$count = preg_match_all( "/Category\\.([^: ]+) /", $line, $matches );	
	global $StopWatch;
#	$StopWatch[] = "$count in $line<br/>";
	foreach($matches[1] as $cat) {
	  if( preg_match($ListCategories_IncludeCategories, $cat) &&
	      !preg_match($ListCategories_ExcludeCategories, $cat) ) {
#	  echo "$cat<br/>";
	    // count how often a Category link appears
	    if( isset($pagelist[$cat]) )
	      $pagelist[$cat]++;
	    else
	      $pagelist[$cat]=1;
	    
	    // create category page
	    if( $ListCategories_CreatePages && !PageExists("Category.$cat") ) {
	      $page = ReadPage("Site.ListCategoriesDefaultPage");
	      if( $page ) $page = array("text"=>"(:keywords $cat:)\n","keywords"=>"$cat");
	      WritePage("Category.$cat",$page);
	    }
	  }
	}
      }
    }
    fclose($fp);
    StopWatch('ListCategories end');
  }

  return $pagelist;
}


function ListCategories( $format, $where ) {
    $pagelist = GetCategoriesCountList( $where );
    // markup
    global $ListCategories_MarkupFunctions;
    if( isset($ListCategories_MarkupFunctions[$format]) )
        $fun = $ListCategories_MarkupFunctions[$format];
    else
        $fun = $ListCategories_MarkupFunctions["simple"];
    return $fun( $pagelist );
}

$HandleActions["pageindex"] = "ListCategoriesIndex";
function ListCategoriesIndex( $pagename, $auth = 'read' ) {
  $opt = array( "order"=>"-ctime" );
  $pagelist = array_values(MakePageList( "Main/HomePage", $opt, 0 ));
#  print_r( $pagelist );
  register_shutdown_function('flush');
  register_shutdown_function('PageIndexUpdate', $pagelist, getcwd());
  
  return HandleBrowse( $pagename, $auth );
}