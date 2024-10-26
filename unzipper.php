<?php
define('VERSION', '0.1.1');
$timestart = microtime(TRUE);
$GLOBALS['status'] = array();
$unzipper = new Unzipper;
if (isset($_POST['dounzip'])) {
  $archive = isset($_POST['zipfile']) ? basename($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? basename($_POST['extpath']) : '';
  $unzipper->prepareExtraction($archive, $destination);
}
if (isset($_POST['dozip'])) {
  $zippath = !empty($_POST['zippath']) ? basename($_POST['zippath']) : '.';
  $zipfile = 'zipper-' . date("Y-m-d--H-i") . '.zip';
  Zipper::zipDir($zippath, $zipfile);
}
$timeend = microtime(TRUE);
$time = round($timeend - $timestart, 4);
class Unzipper {
  public $localdir = '.';
  public $zipfiles = array();
  public function __construct() {
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== FALSE) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['zip', 'gz', 'rar'])) {
          $this->zipfiles[] = $file;
        }
      }
      closedir($dh);
      if (!empty($this->zipfiles)) {
        $GLOBALS['status'] = array('info' => '.zip, .gz, or .rar files found, ready for extraction');
      } else {
        $GLOBALS['status'] = array('info' => 'No archives found. Zipping functionality only.');
      }
    }
  }
  public function prepareExtraction($archive, $destination = '') {
    $extpath = empty($destination) ? $this->localdir : $this->localdir . '/' . $destination;
    if (!is_dir($extpath)) {
      mkdir($extpath, 0755, true);
    }
    if (in_array($archive, $this->zipfiles)) {
      self::extract($archive, $extpath);
    }
  }
  public static function extract($archive, $destination) {
    $ext = strtolower(pathinfo($archive, PATHINFO_EXTENSION));
    switch ($ext) {
      case 'zip':
        self::extractZipArchive($archive, $destination);
        break;
      case 'gz':
        self::extractGzipFile($archive, $destination);
        break;
      case 'rar':
        self::extractRarArchive($archive, $destination);
        break;
    }
  }
  public static function extractZipArchive($archive, $destination) {
    if (!class_exists('ZipArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: PHP version does not support unzip functionality.');
      return;
    }
    $zip = new ZipArchive;
    if ($zip->open($archive) === TRUE) {
      if (is_writeable($destination)) {
        $zip->extractTo($destination);
        $zip->close();
        $GLOBALS['status'] = array('success' => 'Files unzipped successfully.');
      } else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable.');
      }
    } else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read the zip archive.');
    }
  }
  public static function extractGzipFile($archive, $destination) {
    if (!function_exists('gzopen')) {
      $GLOBALS['status'] = array('error' => 'Error: PHP does not support gzip.');
      return;
    }
    $filename = pathinfo($archive, PATHINFO_FILENAME);
    $gzipped = gzopen($archive, "rb");
    $file = fopen($destination . '/' . $filename, "w");
    while ($string = gzread($gzipped, 4096)) {
      fwrite($file, $string);
    }
    gzclose($gzipped);
    fclose($file);
    if (file_exists($destination . '/' . $filename)) {
      $GLOBALS['status'] = array('success' => 'File unzipped successfully.');
      if (pathinfo($destination . '/' . $filename, PATHINFO_EXTENSION) == 'tar') {
        $phar = new PharData($destination . '/' . $filename);
        if ($phar->extractTo($destination)) {
          $GLOBALS['status'] = array('success' => 'Extracted tar.gz successfully.');
          unlink($destination . '/' . $filename);
        }
      }
    } else {
      $GLOBALS['status'] = array('error' => 'Error unzipping file.');
    }
  }
  public static function extractRarArchive($archive, $destination) {
    if (!class_exists('RarArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: PHP does not support Rar functionality.');
      return;
    }
    if ($rar = RarArchive::open($archive)) {
      if (is_writeable($destination)) {
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
          $entry->extract($destination);
        }
        $rar->close();
        $GLOBALS['status'] = array('success' => 'Files extracted successfully.');
      } else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable.');
      }
    } else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read .rar archive.');
    }
  }
}
class Zipper {
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);
    while (FALSE !== $f = readdir($handle)) {
      if ($f != '.' && $f != '..') {
        $filePath = "$folder/$f";
        $localPath = substr($filePath, $exclusiveLength);
        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        } elseif (is_dir($filePath)) {
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }
  public static function zipDir($sourcePath, $outZipPath) {
    $pathInfo = pathinfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];
    $zip = new ZipArchive();
    $zip->open($outZipPath, ZipArchive::CREATE);
    $zip->addEmptyDir($dirName);
    self::folderToZip($sourcePath, $zip, strlen("$parentPath/"));
    $zip->close();
    $GLOBALS['status'] = array('success' => 'Archive created: ' . $outZipPath);
  }
}
?>
