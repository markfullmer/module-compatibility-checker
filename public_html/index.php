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
} else {
  echo '<h3>Invalid <code>composer.json</code> file</h3>';
}
echo '
<div class="container">
  <form action="//' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] . '" method="POST">
    <div class="row">
      <div class="twelve columns">
        <br>
        <label>
          <strong>Target Drupal version</strong>
          <input name="version" type="number" size="4" value="' . $version . '" />
        </label>
      </div>
    </div>
    <div class="row">
      <div class="six columns">
        <h4><label for="json">Paste a valid <code>composer.json</code> below</label></h4>
        <textarea id="json" class="u-full-width textbox" name="json">' . $json . '</textarea>
        <input type="submit" name="submit" value="Check compatibility" />
      </div>
      <div class="six columns">
        <h4>Compatibility Summary</h4>' .
  Check::buildHTMLTable($output['projects'], $version) . '
        <h4><label for="lock">Diff of potential changes</label></h4>
        <div class="u-full-width code">' . $output['diff'] . '</div>';
?>
      </div>
    </div>
  </form>
</div>
</body>
</html>
