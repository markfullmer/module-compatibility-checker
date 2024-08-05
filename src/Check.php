<?php

namespace markfullmer;

use z4kn4fein\SemVer\Version;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;

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
    $current = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $output = [];
    foreach ($composer['require'] as $project => $constraint) {
      if (str_starts_with($project, 'drupal/')) {
        $project = substr($project, '7');
        // Exclude Drupal core variations;
        if (str_starts_with($project, 'core')) {
          continue;
        }
        # Schema reference: https://www.drupal.org/drupalorg/docs/apis/update-status-xml
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
            # Check if core compatibility includes min release such as >9.4
            # e.g. the very popular https://www.drupal.org/project/blazy or
            # range such as >=8.9 <12 for https://www.drupal.org/project/key 
            # No module should have just <12 as core compatibility
            if (str_contains((string) $release->release->core_compatibility, ">")) {

              # Explode the compatibility boundaries
              $bounds = preg_split('/(-?[0-9]+\\.?[0-9]*)/', $release->release->core_compatibility, -1, PREG_SPLIT_DELIM_CAPTURE);

              # Cast everything into the same math space
              $lowerBound = (float) $bounds[1];
              $versionFloat = floatval($version);

              # Test for upper bound
              if (str_contains((string) $release->release->core_compatibility, "<")) {

                # Set the upper bound since it exists
                $upperBound = (float) $bounds[3];
                
                if (($versionFloat >= $lowerBound) && ($versionFloat < $upperBound)) {
                  $target_version = (string) $release->release->version;
                  $compatibility = (string) $release->release->core_compatibility;
                  break;
                }
              } else {
                if ($versionFloat >= $lowerBound) {
                  $target_version = (string) $release->release->version;
                  $compatibility = (string) $release->release->core_compatibility;
                  break;
                }
              }
            }
            if (str_contains((string) $release->release->core_compatibility, "^$version")) {
              $target_version = (string) $release->release->version;
              $compatibility = (string) $release->release->core_compatibility;
              break;
            }
          }
        }
        if ($target_version !== FALSE) {
          if (!str_starts_with($constraint, '^')) {
            $composer['require']["drupal/$project"] = $target_version;
          }
          elseif (str_starts_with($target_version, '8.x-')) {
            $target_version = substr($target_version, 4);
            $composer['require']["drupal/$project"] = "^" . $target_version;
          }
          elseif (!str_contains($target_version, '.x')) {
            $target = Version::parse($target_version);
            $major = $target->getMajor();
            $minor = $target->getMinor();
            $composer['require']["drupal/$project"] = "^" . $major . "." . $minor;
          }
          else {
            $composer['require']["drupal/$project"] = $target_version;
          }

          $output['projects'][$project] = [
            'latest' => $target_version,
            'compatibility' => $compatibility,
            'compatible' => 'Yes',
          ];
        }
        else {
          $output['projects'][$project] = [
            'latest' => $latest_version,
            'compatibility' => $latest_compatibility,
            'compatible' => 'No',
          ];
        }
      }

    }

    $proposed = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $output['diff'] = self::getDiff($current, $proposed);
    $output['proposed'] = $proposed;
    return $output;
  }

  public static function getDiff($current, $proposed) {
    $diff = DiffHelper::calculate($current, $proposed);
    $lines = explode(PHP_EOL, $diff);
    foreach ($lines as &$line) {
      if (str_starts_with($line, '-')) {
        $line = '<div class="diff--remove">' . $line . '</div>';
      }
      elseif (str_starts_with($line, '+')) {
        $line = '<div class="diff--add">' . $line . '</div>';
      }
      else {
        $line = '<div>' . $line . '</div>';
      }
    }
    return implode("", $lines);
  }

  public static function buildHTMLTable($projects, $version) {
    $table = [];
    $table[] = '<table id="compatresults"><thead><tr><th>Component</th><th>D' . $version . ' compatible?<th>Latest version</th><th>Core compatibility</th></tr></thead>';
    foreach ($projects as $project => $data) {
      $table[] = '<tr class="' . strtolower($data['compatible']) . '"><td><a href="https://www.drupal.org/project/' . $project . '">'. $project . '</a></td><td>' . $data['compatible'] . '</td><td>' . $data['latest'] . '</td><td>' . $data['compatibility'] . '</td></tr>';
    }
    $table[] = '</table>';
    return implode("", $table);
  }

}
