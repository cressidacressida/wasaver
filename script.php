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

$required_options = ["db_host" => NULL,
                     "db_user" => NULL,
                     "db_password" => NULL];
$options = array_merge($required_options, ["contact_name" => NULL, ]);
$config_file = fopen($config_file_name, "r") or die("Unable to open config file '{$config_file_name}'");
while (($line = fgets($config_file)) !== false) {
  $line = trim($line);
  if(preg_match('/^#/', $line))
    continue;
  foreach($options as $option => $value) {
    $regexp = '/^'.$option.' *= */';
    if(preg_match($regexp, $line)) {
      $options[$option] = preg_replace($regexp, '', $line);
      if(preg_match("/^'.+'$/", $options[$option]) || preg_match('/^".+"$/', $options[$option]))
        $options[$option] = substr($options[$option], 1, -1);
    }
  }
}
fclose($config_file);

foreach($required_options as $option => $value)
  if($options[$option] === NULL || $options[$option] === '')
    die("Missing required option '{$option}' in config file '{$config_file_name}'");

if(! (isset($contact_name) && $contact_name !== '')) {
  if($options["contact_name"] === NULL || $options["contact_name"] === '')
    die("Unable to determine contact name");
  else
    $contact_name = $options["contact_name"];
}

$contact_safe_name = preg_replace('/[^a-zA-Z0-9_]/', '', $contact_name);
$path = 'media_'.$contact_safe_name;
$db_name = $contact_safe_name."_chat";
$page_title = "chat with {$contact_name}";

$query = "
SELECT id, DATE_FORMAT(datetime, '%%r, %%d/%%m/%%Y') AS datetime, DATE_FORMAT(datetime, '%%a %%d/%%m/%%Y') AS date, sender,
quoting, forwarded, COALESCE(IF(messages.revoked, 'revoked', NULL), t1.type, t2.type, t3.type, t4.type, t5.type, t6.type) AS type, text, media_type,
COALESCE(t2.filename, t3.filename) AS filename, caption, latitude, longitude, contact, notification FROM
(SELECT *                           FROM messages %s)           messages      LEFT JOIN
(SELECT *, 'text'           AS type FROM text_messages)         t1 USING (id) LEFT JOIN
(SELECT *, 'audio'          AS type FROM audio_messages)        t2 USING (id) LEFT JOIN
(SELECT *, 'media'          AS type FROM media_messages)        t3 USING (id) LEFT JOIN
(SELECT *, 'geo'            AS type FROM geo_messages)          t4 USING (id) LEFT JOIN
(SELECT *, 'vcard'          AS type FROM vcard_messages)        t5 USING (id) LEFT JOIN
(SELECT *, 'notification'   AS type FROM notification_messages) t6 USING (id)
ORDER BY messages.datetime, t6.type IS NULL";

$url_regexp = '%(?:(?:(?:https?|ftp):)?\/\/)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z0-9\x{00a1}-\x{ffff}][a-z0-9\x{00a1}-\x{ffff}_-]{0,62})?[a-z0-9\x{00a1}-\x{ffff}]\.)+(?:[a-z\x{00a1}-\x{ffff}]{2,}\.?))(?::\d{2,5})?(?:[/?#]\S*)?%iuS';

function make_links($text) {
  global $url_regexp;
  return preg_replace($url_regexp, '<a href="$0" target="_blank">$0</a>', $text);
}

function print_dates() {
  global $mysqli;
  $result = $mysqli->query("SELECT DISTINCT DATE_FORMAT(datetime, '%a %d/%m/%Y') AS date FROM messages ORDER BY messages.datetime") or die($mysqli->error);
  if($row = $result->fetch_assoc()) {
    $tmp = strtr(substr($row["date"], 4), array('/' => ''));
    echo "<a class='date-link' id='{$tmp}_link' href='#' onclick='scrollToTop(\"main\")'>{$row["date"]}</a>";
  }
  while($row = $result->fetch_assoc()) {
    $tmp = strtr(substr($row["date"], 4), array('/' => ''));
    echo "<a class='date-link' id='{$tmp}_link' href='#' onclick='scrollToId(\"{$tmp}\")'>{$row["date"]}</a>";
  }
  $result->free();
}

function print_inner_message($row) {
  global $path;
  switch($row["type"]) {
  case "text":
    echo nl2br(make_links($row["text"]));
    break;
  case "revoked":
    echo 'Message deleted';
    break;
  case "audio":
    echo '<audio controls><source src="'.$path.'/'.$row["filename"].'"></audio>';
    break;
  case "media":
    switch($row["media_type"]) {
    case "image":
      $image_src = $path.'/'.$row["filename"];
      echo '<a href="'.$image_src.'"><img src="'.$image_src.'"></a>';
      if($row["caption"] !== '')
        echo '<div class="caption">'.nl2br(make_links($row["caption"])).'</div>';
      break;
    case "video":
      echo '<video controls><source src="'.$path.'/'.$row["filename"].'"></video>';
      break;
    }
    break;
  case "geo":
    echo '<div class="coordinates">'.$row["latitude"]." ".$row["longitude"].'</div>';
    echo '<a href="https://www.google.com/maps/place/'.$row["latitude"].','.$row["longitude"].'" target="_blank">See in Google Maps</a>';
    break;
  case "vcard":
    echo nl2br($row["contact"]);
    break;
  case "notification":
    echo $row["notification"];
    break;
  }
}

function print_message($row) {
  global $mysqli;
  global $query;

  if($row["type"] == "notification")
    $classes = "not-real-message notification";
  else
    $classes = "real-message ".($row["sender"] == "You" ? "from-me" : "not-from-me");
  echo "<div class='container {$classes}'";
  if($row["type"] !== "notification")
    echo " id='{$row["id"]}'";
  echo '>';

  if($row["type"] !== "notification") {
    echo '<div class="message-header-container"><div class="timestamp">'.$row["datetime"].'</div>';
    if($row["forwarded"])
      echo '<div class="attribute">Forwarded</div>';
    echo '</div>';
  }

  if($row["quoting"]) {
    $tmp = $mysqli->query(sprintf($query, "WHERE messages.id LIKE '%".$row["quoting"]."'")) or die($mysqli->error);
    $tmp_row = $tmp->fetch_assoc();
    echo '<a href="#'.$tmp_row["id"].'"><div class="quote-container"><div class="quote-header">';
    echo $tmp_row["sender"].' @ '.$tmp_row["datetime"];
    echo '</div><div class="quote">';
    print_inner_message($tmp_row);
    $tmp->free();
    echo '</div></div></a>';
  }

  echo '<div class="message '.$row["type"].($row["type"] == "media" ? " ".$row["media_type"] : "").'">';
  print_inner_message($row);
  echo '</div></div>';
}
