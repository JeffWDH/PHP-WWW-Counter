<?php
// PHP-WWW-Counter v1.0
// https://github.com/JeffWDH/PHP-WWW-Counter

// A list of color names and their respective RGB values
include "colors.inc";

// Default colors
$font_color = "lightskyblue"; // Font color
$background_color = "darkdarkblue"; // Background color
$frame_color = "midnightblue"; // Frame color

// Paths (no trailing forward slash)
// Counter data file must be pre created and the web server requires read/write access
// echo 1 > SomeDataFile and chmod to allow server access
$counter_data_dir = "/var/www/html/Counter/countdata";
$font_dir = "/var/www/html/Counter/fonts";

// Default settings
$font = "SevenSegment"; // .ttf located in font_dir
$font_size = 36; // Font size (1-300)
$font_pad = 25;  // Number of pixels to pad the image around the text
$frame_thickness = 5; // Frame thickness in pixels
$number_format = 1; // 0 = off, 1 = add commas to number
$hidden = 0; // 0 = off, 1 = send 1x1 transparent imag

// Avoid counting repeated visists within $cookie_expiration seconds
$cookie_expiration = 60;

// Begin

if (isset($_GET['datafile'])) {
  $counter_data_file_name = sanitizeInputFilename($_GET['datafile']);
  $counter_data_file = "$counter_data_dir" . "/" . $counter_data_file_name;
  if (!file_exists($counter_data_file)) {
    die("Counter file does not exist.");
  }
} else {
  die("No counter data file defined.");
}

if (isset($_GET['font'])) {
  $font = sanitizeInputFilename($_GET['font']);
}

$font_file = $font_dir . "/" . $font . ".ttf";
if (!file_exists($font_file)) {
  die("Font does not exist.");
}

if (isset($_GET['font_size'])) {
  $font_size = sanitizeInputNumber($_GET['font_size']);
  if ($font_size > 300) {
    die("Font size too large.");
  }
}

if (isset($_GET['font_color'])) {
  $font_color = sanitizeInputText($_GET['font_color']);
}

if (isset($_GET['background_color'])) {
  $background_color = sanitizeInputText($_GET['background_color']);
}

if (isset($_GET['number_format'])) {
  if ($_GET['number_format'] == 1) {
    $number_format = 1;
  } else {
    $number_format = 0;
  }
}

if (isset($_GET['hidden'])) {
  if ($_GET['hidden'] == 1) {
    $hidden = 1;
  } else {
    $hidden = 0;
  }
}

if (isset($_GET['frame_thickness'])) {
  $frame_thickness = sanitizeInputNumber($_GET['frame_thickness']);
}

if (isset($_GET['frame_color'])) {
  $frame_color = sanitizeInputText($_GET['frame_color']);
}

$count = getCounter("$counter_data_file");

if (!isset($_COOKIE[$counter_data_file_name . "_counter"])) {
  // Cookie not set, increment counter
  $count = incCounter("$counter_data_file", $count);

  // Set cookie
  setcookie($counter_data_file_name . "_counter", "cooldown", time() + $cookie_expiration, "/");
}

// If number_format is enabled, apply formatting
if ($number_format == 1) {
  $count_string = number_format($count);
} else {
  $count_string = $count;
}

if ($hidden == 0) {
  // Determine text size
  $count_size = imagettfbbox($font_size, 0, $font_file, $count_string);
  $count_size_x = $count_size[4];
  $count_size_y = abs($count_size[5]);

  // Determine image size
  if ($frame_thickness == 0) {
    $image_size_x = $count_size_x + $font_pad;
    $image_size_y = $count_size_y + $font_pad;
  } else {
    $image_size_x = $count_size_x + $frame_thickness + $font_pad;
    $image_size_y = $count_size_y + $frame_thickness + $font_pad;
  }

  // Create image object
  $image = imagecreatetruecolor($image_size_x, $image_size_y);

  // Image antialiasing
  imageantialias($image, false);

  // Define colors
  // Black (RGB 0, 0, 0) is a special case as the object creation will return 0
  // The rest will return >0 if successful

  $background_color_obj = imagecolorallocate($image, $colors[$background_color][0], $colors[$background_color][1], $colors[$background_color][2]);
  if (($background_color != "black") && ($background_color_obj == 0)) {
    die("Cannot allocate background color.");
  }

  $font_color_obj = imagecolorallocate($image, $colors[$font_color][0], $colors[$font_color][1], $colors[$font_color][2]);
  if (($font_color != "black") && ($font_color_obj == 0)) {
    die("Cannot allocate font color.");
  }

  $frame_color_obj = imagecolorallocate($image, $colors[$frame_color][0], $colors[$frame_color][1], $colors[$frame_color][2]);
  if (($frame_color != "black") && ($frame_color_obj == 0)) {
    die("Cannot allocate frame color.");
  }

  // Fill background
  if ($frame_thickness == 0) {
    imagefilledrectangle($image, 0, 0, $image_size_x, $image_size_y, $background_color_obj);
  } else {
    imagefilledrectangle($image, 0, 0, $image_size_x, $image_size_y, $frame_color_obj);
    imagefilledrectangle($image, ($frame_thickness/2), ($frame_thickness/2), $image_size_x-($frame_thickness/2), $image_size_y-($frame_thickness/2), $background_color_obj);
  }

  // Add the text
  imagettftext($image, $font_size, 0, ((($image_size_x + $count_size_x) / 2) - $count_size_x), (($image_size_y + $count_size_y) / 2), $font_color_obj, $font_file, $count_string);
} else {
  // Create 1x1 transparent image
  $image = imagecreatetruecolor(1, 1);
  imagealphablending($image, false);
  imagesavealpha($image, true);
  $bgcolor = imagecolorallocatealpha($image, 0, 0, 0, 127);
  imagefill($image, 0, 0, $bgcolor);
}

// Send picture to client
header("Content-Type: image/png");
imagepng($image);
imagedestroy($image);

function getCounter($countfile) {
  $oldcount = trim(file_get_contents($countfile)) or die ("Unable to read counter data file.");
  return $oldcount;
}

function incCounter($countfile, $oldcount) {
  $newcount = (int)$oldcount + 1;
  $stream = fopen($countfile, "w") or die("Unable to write to counter data file.");
  fwrite($stream, strval($newcount));
  fclose($stream);
  return $newcount;
}

function sanitizeInputFilename($input) {
  return preg_replace("/[^[:alnum:]._-]/u", '', $input);
}

function sanitizeInputText($input) {
  return preg_replace("/[^[:alnum:]]/u", '', $input);
}

function sanitizeInputNumber($input) {
  return preg_replace("/[^[:digit:]]/u", '', $input);
}

?>
