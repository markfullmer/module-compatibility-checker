<?php

namespace markfullmer;

use z4kn4fein\SemVer\Version;

/**
 * Class Check.
 *
 * Given composer.json, generate a report of module compatibility.
 *
 * @author markfullmer <mfullmer@gmail.com>
 *
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 */
class Check {

  /**
   * Main generation method.
   *
   * @param string $json
   *   The composer.json file as a string.
   * @param string $version
   *   The target Drupal version.
   *
   * @return array
   *   The nested array of dependencies.
   */
  public static function process($json, int $version) {
    $composer = json_decode($json, TRUE);
    $output = [];
    foreach (array_keys($composer['require']) as $key) {
      if (str_starts_with($key, 'drupal/')) {
        $project = substr($key, '7');
        // Exclude Drupal core variations;
        if (str_starts_with($project, 'core')) {
          continue;
        }
        $url = "https://updates.drupal.org/release-history/$project/current";
        $xml_content = file_get_contents($url);
        $xml = simplexml_load_string($xml_content);
        $target_version = FALSE;
        $latest_version = NULL;
        $latest_compatibility = NULL;
        foreach ($xml->releases as $release) {
          if (isset($release->release->core_compatibility)) {
            if ($latest_version === NULL) {
              $latest_compatibility = (string) $release->release->core_compatibility;
              $latest_version = (string) $release->release->version;
            }
            if (str_contains((string) $release->release->core_compatibility, "^$version")) {
              $target_version = (string) $release->release->version;
              $compatibility = (string) $release->release->core_compatibility;
              break;
            }
          }
        }
        if ($target_version !== FALSE) {
          if (!str_contains($target_version, '.x')) {
            $target = Version::parse($target_version);
            $replacement = $target->getMajor();
            $composer['require']["drupal/$project"] = "^" . $replacement . ".0";
          }
          else {
            $composer['require']["drupal/$project"] = $target_version;
          }

          $output['compatible'][$project] = [
            'latest' => $target_version,
            'compatibility' => $compatibility,
          ];
        }
        else {
          $output['incompatible'][$project] = [
            'latest' => $latest_version,
            'compatibility' => $latest_compatibility,
          ];
        }
      }

    }
    $output['suggestions'] = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    return $output;
  }

}
