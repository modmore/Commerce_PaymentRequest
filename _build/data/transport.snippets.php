<?php
$snips = array(
    'commerce_paymentrequest' => 'Front-end implementation of payment requests.',
);

$snippets = array();
$idx = 0;

foreach ($snips as $name => $description) {
    $idx++;
    $snippets[$idx] = $modx->newObject('modSnippet');
    $snippets[$idx]->fromArray(array(
       'name' => $name,
       'description' => $description . ' (Part of Commerce_PaymentRequest)',
       'snippet' => getSnippetContent($sources['snippets'] . strtolower($name) . '.snippet.php')
    ));
}

return $snippets;
