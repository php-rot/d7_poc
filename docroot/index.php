<?php

namespace Rot;

// The drupal .htaccess file really needs to be pulled out to the docroot, so that sucks.

class Bootloader
{
  private static $singleton = null;

  private $immutable_dir;
  private $mutable_dir;
  private $project_config;
  private $active_config = null;
  private $active_partition_path = null;
  private $inactive_partition_path = null;

  protected function __construct()
  {
    $this->setImmutableDirectory();
    $this->loadProjectConfiguration();
    $this->setMutableDirectory();
    $this->setActiveConfiguration();
  }

  /**
   * @return Bootloader
   */
  public static function getInstance() {
    if (Bootloader::$singleton === null) {
      Bootloader::$singleton = new Bootloader();
    }

    return Bootloader::$singleton;
  }

  protected function setImmutableDirectory() {
    $this->immutable_dir = realpath(dirname(__FILE__));
  }

  public function getImmutableDirectory() {
    return $this->immutable_dir;
  }

  protected function loadProjectConfiguration() {
    // TODO: should this be .php for free code-as-cacheability?
    $path = $this->immutable_dir . '/project.json';
    $config_raw = file_get_contents($path);
    if (!$config_raw) {
      die('Rot: Missing project configuration: ' . $path);
    }
    $this->project_config = json_decode($config_raw);
  }

  protected function setMutableDirectory() {
    $mutable_directory = $this->project_config->mutable_directory;
    if (substr($mutable_directory, 0, 1) != '/') {
      $this->mutable_dir = realpath($this->immutable_dir . DIRECTORY_SEPARATOR . $mutable_directory);
    } else {
      $this->mutable_dir = $mutable_directory;
    }
  }

  protected function getActiveConfigFilename() {
    return $this->mutable_dir . DIRECTORY_SEPARATOR . 'active.json';
  }

  protected function setActiveConfiguration() {
    // TODO: should this be .php for free code-as-cacheability?
    $active_config_path = $this->getActiveConfigFilename();
    if (file_exists($active_config_path)) {
      $raw = file_get_contents($active_config_path);
      if (strlen($raw)) {
        // TODO: handle decode failure
        $this->active_config = json_decode($raw);
      }
    }

    if ($this->active_config === null) {
      // Such as on first run.
      $this->toggleActivePartition();
    }
  }

  protected function toggleActivePartition() {
    // TODO: provide public API to *safely* change to the other partition by having the bootloader verify that the
    // other partition is consistent with the current manifest.
    if ($this->active_config === null) {
      $this->active_config = new \stdClass();
      $this->active_config->partition = 'b';
    }

    $this->active_config->partition = $this->getInactivePartition();
    $this->active_partition_path = $this->inactive_partition_path = null;

    $raw = json_encode($this->active_config);
    if (file_put_contents($this->getActiveConfigFilename(), $raw) !== strlen($raw)) {
      die('Rot: Failed to write active configuration to file: ' . $this->getActiveConfigFilename());
    }
  }

  protected function computeMutablePaths() {
    $this->active_partition_path = realpath($this->mutable_dir . DIRECTORY_SEPARATOR . $this->getActivePartition());
    $this->inactive_partition_path = realpath($this->mutable_dir . DIRECTORY_SEPARATOR . $this->getInactivePartition());
  }

  public function getActivePartition() {
    return $this->active_config->partition;
  }

  public function getInactivePartition() {
    if ($this->getActivePartition() === 'b') {
      return 'a';
    } else {
      return 'b';
    }
  }

  public function getActivePartitionPath() {
    if ($this->active_partition_path === null) {
      $this->computeMutablePaths();
    }
    return $this->active_partition_path;
  }

  public function getInactivePartitionPath() {
    if ($this->inactive_partition_path === null) {
      $this->computeMutablePaths();
    }
    return $this->inactive_partition_path;
  }
}


chdir(Bootloader::getInstance()->getActivePartitionPath());

// TODO: File serving is needed for D7, but not everything. This implies some sort of plugin system.
if (isset($_SERVER['REQUEST_URI'])) {
  $request_path = strtok($_SERVER['REQUEST_URI'], '?');
  $base_path_len = strlen(rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/'));
  // Unescape and strip $base_path prefix, leaving q without a leading slash.
  $path = substr(urldecode($request_path), $base_path_len + 1);
  // TODO: this is PoC level and phenomenally insecure since it can be set to private or source files.
  $try_file = Bootloader::getInstance()->getActivePartitionPath() . DIRECTORY_SEPARATOR . $path;
  if (is_file($try_file)) {
    $type = mime_content_type($try_file);
    $overrides = ['css' => 'text/css', 'js' => 'application/javascript'];
    $filename_parts = explode('.', basename($try_file));
    $ext = array_pop($filename_parts);
    if (! empty($overrides[$ext])) {
      $type = $overrides[$ext];
    }
    header('Content-Type: ' . $type);
    $fh = fopen($try_file, 'r');
    fpassthru($fh);
    fclose($fh);
    exit;
  }
}

include Bootloader::getInstance()->getActivePartitionPath() . DIRECTORY_SEPARATOR . 'index.php';
