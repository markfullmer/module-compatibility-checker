<?php
require '../vendor/autoload.php';

use JsonSchema\Validator;
use markfullmer\Check;

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'head.html';
if (isset($_GET['utdk'])) {
  $default = file_get_contents('../data/utdk.json');
}
else {
  $default = file_get_contents('../data/composer.json');
}

// @phpcs:ignore
$json = $_POST['json'] ?? $default;
// @phpcs:ignore
$version = $_POST['version'] ?? '12';

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
} else {
  echo '<h3>Invalid <code>composer.json</code> file</h3>';
}
echo '
<div class="container">
    <div class="row">
      <div class="six columns">
        <form action="//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '" method="POST">
        <label>
          <strong>Target Drupal version</strong>
          <input name="version" type="number" size="4" value="' . $version . '" />
        </label>
        <h4><label for="json">Paste a valid <code>composer.json</code> below</label></h4>
        <textarea id="json" class="u-full-width textbox" name="json">' . $json . '</textarea>
        <input type="submit" name="submit" value="Check compatibility" />
        </form>
      </div>
      <div class="six columns">
        <h4>Compatibility Summary</h4>
        <p><strong>Please note</strong> there may be newer dev versions with the target core compatibility&mdash;this only checks current releases.</p>' .
  Check::buildHTMLTable($output['projects'], $version) . '
        <h4><label for="lock">Proposed new composer.json</label></h4>
        <button onclick="copyToClipboard()">Copy composer.json</button>
        <textarea id="proposed" class="u-full-width textbox" name="json">' . $output['proposed'] . '</textarea>';
?>
      </div>
    </div>
</div>
</body>
</html>
