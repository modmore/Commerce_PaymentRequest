<?php
/**
 * @var modX $modx
 * @var array $scriptProperties
 */


// Instantiate the Commerce class
$path = $modx->getOption('commerce.core_path', null, MODX_CORE_PATH . 'components/commerce/') . 'model/commerce/';
$params = ['mode' => $modx->getOption('commerce.mode')];
/** @var Commerce|null $commerce */
$commerce = $modx->getService('commerce', 'Commerce', $path, $params);
if (!($commerce instanceof Commerce)) {
    return '<p class="error">Oops! It is not possible to pay for this request at the moment. Please try again later.</p>';
}

if ($commerce->isDisabled()) {
    return $commerce->adapter->lexicon('commerce.mode.disabled.message');
}

$ref = array_key_exists('ref', $_GET) ? (string)$_GET['ref'] : '';
if (empty($ref)) {
    return '<p class="error">Missing payment request reference.</p>';
}

$request = $commerce->adapter->getObject('prPaymentRequest', ['reference' => $ref]);
if (!$request) {
    return '<p class="error">Could not locate the payment request. Please double check the URL or contact customer support.</p>';
}

/** @var comOrder $order */
$order = $commerce->adapter->getObject('comOrder', ['id' => $request->get('order'), 'test' => $commerce->isTestMode()]);
if (!$order) {
    return '<p class="error">Relevant order not found. Please contact customer support.</p>';
}

/** @var comPaymentMethod[] $methods */
$methods = [];

$c = $commerce->adapter->newQuery('comPaymentMethod');
$c->sortby('sortorder');
$c->sortby('name');

/** @var comPaymentMethod $method */
foreach ($commerce->adapter->getIterator('comPaymentMethod', $c) as $method) {
    // Skip if the payment method is not available based on configured availability rules
    if (!$method->isAvailableForOrder($this->order)) {
        continue;
    }

    $gateway = $method->getGatewayInstance();
    if (!$gateway || $gateway instanceof \modmore\Commerce\Gateways\Manual) {
        continue;
    }

    $methods[$method->get('id')] = $method;
}

$chosen = array_key_exists('payment_method', $_POST) ? (int)$_POST['payment_method'] : false;
if ($chosen !== false && array_key_exists($chosen, $methods)) {
    $chosenMethod = $methods[$chosen];

    $transaction = $chosenMethod->initiateTransaction($order, $request->get('amount'));
    if (!$transaction) {
        return '<p class="error">Failed initiating transaction to chosen payment method.</p>';
    }

    $transaction->log('Transaction initiated for ' . $transaction->get('amount_formatted') . ' (including ' . $transaction->get('fee_formatted') . ' fee) with payment method ' . $method->get('name') . ' from payment request ' . $request->get('id'), comTransactionLog::SOURCE_CHECKOUT);
    $order->log('commerce.log.initiated_transaction', false, [
        'method' => $method->get('id'),
        'method_name' => $method->get('name'),
        'transaction' => $transaction->get('id')
    ]);

}

var_dump($request->toArray());