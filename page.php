<?php
/*
wasaver
Copyright (C) 2020  cressidacressida

This file is part of wasaver.

wasaver is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

wasaver is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with wasaver.  If not, see <https://www.gnu.org/licenses/>.
*/
?>
<!DOCTYPE html>
<?php
### chat to be displayed
# this setting overrides the contact_name variable from the configuration file
#$contact_name = "contact name";

$config_file_name = "wasaver.conf";
?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require('script.php');
$mysqli = new mysqli($options["db_host"], $options["db_user"], $options["db_password"], $db_name);
?>
<html>
<head>
  <meta charset="utf-8">
  <link rel="stylesheet" href="styles.css">
  <script type="text/javascript" src="script.js" defer></script>
  <title><?php echo $page_title; ?></title>
</head>
<body>
  <header>
    <div>
      <h1>
        <?php echo $page_title; ?>
      </h1>
    </div>
    <div id="toolbar">
      <button onclick="scrollToTop('main')">
        <div class="arrow up"></div>
      </button>
      <button onclick="scrollToBottom('main')">
        <div class="arrow down"></div>
      </button>
      <div class="dropdown-wrapper">
        <button class="dropdown-button" id="dropdown-button-date" onclick="showDropDownDate()">
          <div>
            <div class="hamburger"></div>
            <div class="hamburger"></div>
            <div class="hamburger"></div>
          </div>
        </button>
        <div class="dropdown-content" id="dropdown-content-date">
          <?php print_dates(); ?>
        </div>
      </div>
    </div>
  </header>
  <div id="page">
    <div id="sidebar">
      <?php print_dates(); ?>
    </div>
    <div id="main">
      <?php
      $result = $mysqli->query(sprintf($query, '')) or die($mysqli->error);
      $date = '';
      while($row = $result->fetch_assoc()) {
        if($date !== $row["date"]) {
          $tmp = strtr(substr($row["date"], 4), array('/' => ''));
          if($date === '')
            echo '<a href="#" class="container not-real-message date" id="'.$tmp.'">';
          else
            echo '<hr id="'.$tmp.'"><a href="#'.$tmp.'" class="container not-real-message date">';
          $date = $row["date"];
          echo $date.'</a>';
        }
        print_message($row);
      }
      $result->free();
      ?>
    </div>
  </div>
</body>
</html>
<?php $mysqli->close(); ?>
