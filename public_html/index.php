<?php

require '../vendor/autoload.php';

use JsonSchema\Validator;
use markfullmer\Check;

include 'head.html';
$default = file_get_contents('../data/composer.json');


// @phpcs:ignore
$json = $_POST['json'] ?? $default;
// @phpcs:ignore
$version = $_POST['version'] ?? '11';

$print = TRUE;
$validator = new Validator();
$data = json_decode($json);
$output = [
  'suggestions' => '',
];
$schema = (object) ['$ref' => 'https://getcomposer.org/schema.json'];
$validator->validate($data, $schema);
if (!$validator->isValid()) {
  $print = FALSE;
  foreach ($validator->getErrors() as $error) {
    echo '<pre>';
    printf("[%s] %s\n", $error['property'], $error['message']);
    echo '</pre>';
  }
}

if ($print) {
  $output = Check::process($json, $version);
}
else {
  echo '<h3>Invalid <code>composer.json</code> file</h3>';
}
echo '
<div class="container">
  <form action="//' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] . $_SERVER['SERVER_PORT'] . '" method="POST">
    <div class="row">
      <div class="twelve columns">
        <br>
        <label>
          <strong>Target Drupal version (e.g., "11")</strong>
          <input name="version" type="number" value="' . $version . '" />
        </label>
      </div>
    </div>
    <div class="row">
      <div class="six columns">
        <label for="json">Current <code>composer.json</code> file</label>
        <textarea id="json" class="u-full-width textbox" name="json">' . $json . '</textarea>
      </div>
      <div class="six columns">
        <label for="lock">Changes with available compatible modules</label>
        <textarea disabled id="json" class="u-full-width textbox" name="json">' . $output['suggestions'] . '</textarea>
      </div>
    </div>
    <div class="row">
      <div class="twelve columns">
        <input type="submit" name="submit" value="Check compatibility" />
      </div>
    </div>
  </form>
    <div class="row">
      <div class="six columns">
      <h3>Modules compatible with Drupal ' . $version . '</h3>
      <table><thead><tr><th>Component</th><th>Latest version</th><th>Core compatibility</th></tr></thead>';
if (isset($output['compatible'])) {
  foreach ($output['compatible'] as $project => $v) {
    echo '<tr>
      <td><a href="https://drupal.org/project/' . $project .'">' . $project . '</a></td>
      <td><a href="https://drupal.org/project/' . $project . '/releases/' . $v['latest'] .'">' . $v['latest'] . '</a></td>
      <td>' . $v['compatibility'] . '</td>
    </tr>';
  }
}
echo '</table>';

echo '
      </div>
      <div class="six columns">
      <h3>Modules incompatible with Drupal ' . $version . '</h3>
      <table><thead><tr><th>Component</th><th>Latest version</th><th>Core compatibility</th></tr></thead>';

if (isset($output['incompatible'])) {
  foreach ($output['incompatible'] as $project => $v) {
    echo '<tr>
      <td><a href="https://drupal.org/project/' . $project . '">' . $project . '</a></td>
      <td><a href="https://drupal.org/project/' . $project . '/releases/' . $v['latest'] . '">' . $v['latest'] . '</a></td>
      <td>' . $v['compatibility'] . '</td>
    </tr>';
  }
}

?>
      </div>
    </div>
</div>
</body>
</html>
