<?php
/**
 * @var modX $modx
 * @var array $scriptProperties
 */

use modmore\Commerce\Gateways\Exceptions\TransactionException;
use modmore\Commerce\Gateways\Interfaces\RedirectTransactionInterface;

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

/** @var prPaymentRequest $request */
$request = $commerce->adapter->getObject('prPaymentRequest', ['reference' => $ref]);
if (!$request) {
    return '<p class="error">Could not locate the payment request. Please double check the URL or contact customer support.</p>';
}

/** @var comOrder $order */
$order = $commerce->adapter->getObject('comOrder', ['id' => $request->get('order'), 'test' => $commerce->isTestMode()]);
if (!$order) {
    return '<p class="error">Relevant order not found. Please contact customer support.</p>';
}

$methodId = (int)$modx->getOption('method', $scriptProperties, 0, true);

/** @var comPaymentMethod $method */
$method = $commerce->adapter->getObject('comPaymentMethod', [
    'id' => $methodId,
    'enabled_in_' . ($commerce->isTestMode() ? 'test' : 'live') => true
]);

if (!$method) {
    return '<p class="error">Could not load the payment method. Please contact customer support.</p>';
}

$newAttempt = false;
$transaction = $request->getTransaction();

if (!$transaction) {
    $newAttempt = true;
    $transaction = $method->initiateTransaction($order, $request->get('amount'));
    if (!$transaction) {
        return '<p class="error">Failed initiating transaction to chosen payment method.</p>';
    }
    $transaction->setProperty('payment_request', $request->get('id'));
    $transaction->save();
}

$transaction->log('Transaction initiated for ' . $transaction->get('amount_formatted') . ' (including ' . $transaction->get('fee_formatted') . ' fee) with payment method ' . $method->get('name') . ' from payment request ' . $request->get('id'), comTransactionLog::SOURCE_CHECKOUT);
$order->log('commerce.log.initiated_transaction', false, [
    'method' => $method->get('id'),
    'method_name' => $method->get('name'),
    'transaction' => $transaction->get('id')
]);

$request->set('transaction', $transaction->get('id'));
$request->save();

/** @var \modmore\Commerce\Gateways\Mollie $gateway */
$gateway = $method->getGatewayInstance();
if (!$gateway) {
    return '<p class="error">Could not load the payment gateway. Please contact customer support.</p>';
}

$selfLink = $commerce->adapter->makeResourceUrl($modx->resource->get('id'), '', ['ref' => $ref], 'full');
// Use the gateway to create the transaction
try {
    $result = $newAttempt ? $gateway->submit($transaction, []) : $gateway->returned($transaction, []);
}
catch (TransactionException $e) {
    $request->set('transaction', 0);
    $request->save();
    $errorKey = $newAttempt ? 'commerce.error_creating_transaction' : 'commerce.error_verifying_transaction';
    $message = $modx->getOption('messageFailed', $scriptProperties, '<p>' . $commerce->adapter->lexicon($errorKey, ['name' => $method->get('name'), 'message' => $e->getMessage()]) . ' <a href="%link%">Probeer het opnieuw</a></p>');
    return str_replace('%link%', $selfLink, $message);
}

// Store the reference if we have it
if ($reference = $result->getPaymentReference()) {
    $transaction->log('Transaction reference: ' . $reference, comTransactionLog::SOURCE_CHECKOUT);
    $transaction->set('reference', $reference);
    $transaction->save();

    // Use the API to patch the redirect URL - we can't change this in a neater way
    $client = $gateway->getClient();
    try {
        $molliePayment = $client->payments->get($reference);
        $molliePayment->redirectUrl = $selfLink;
        $molliePayment->update();
    } catch (\Mollie\Api\Exceptions\ApiException $e) {
        $transaction->log('Failed updating redirectUrl for Payment Request; transaction ' . $reference . ' not found: ' . $e->getMessage(), comTransactionLog::SOURCE_CHECKOUT);
    }
}

// Paid? Great! Mark transaction as such, and redirect to the thank you page.
if ($result->isPaid()) {
    if (!$transaction->isCompleted()) {
        if ($newAttempt) {
            $transaction->log('Transaction was immediately confirmed', comTransactionLog::SOURCE_CHECKOUT);
        }
        $transaction->setProperties($result->getExtraInformation());
        $transaction->markCompleted();
        $transaction->save();
        $order->calculate();
    }

    if ($request->get('status') !== prPaymentRequest::STATUS_COMPLETED) {
        $request->set('status', prPaymentRequest::STATUS_COMPLETED);
        $request->set('completed_on', time());
        $request->save();
    }

    return $modx->getOption('messageSuccess', $scriptProperties, '<p class="success">Bedankt, we hebben uw betaling ontvangen.</p>');
}

if ($newAttempt && $result instanceof RedirectTransactionInterface && $result->isRedirect()) {
    $transaction->markProcessing();

    $url = $result->getRedirectUrl();
    $transaction->setProperty('redirectUrl', $url);
    $redirMethod = $result->getRedirectMethod();
    $transaction->setProperty('redirectMethod', $redirMethod);
    $redirData = $result->getRedirectData();
    if (is_array($redirData)) {
        $transaction->setProperty('redirectData', $redirData);
    }

    $transaction->log('Redirecting customer: ' . $redirMethod . ' ' . $url, comTransactionLog::SOURCE_CHECKOUT);
    $transaction->save();

    @session_write_close();
    header('Location: ' . $url);
    exit();
}

// Are we waiting for confirmation, from an off-site or offline payment? Show the pending transaction view.
if ($result->isAwaitingConfirmation()) {
    $transaction->markProcessing();

    $transaction->log('Transaction still awaiting confirmation; showing customer the transaction is pending', comTransactionLog::SOURCE_CHECKOUT);

    $message = $modx->getOption('messageWaitingConfirmation', $scriptProperties, '<p>Uw betaling is gestart, maar nog niet voltooid of verwerkt. Afhankelijk van de gekozen betaalmethode kan dit enkele minuten tot enkele dagen duren. <a href="%link%">Probeer het opnieuw</a></p>');
    $message = str_replace('%link%', $transaction->getProperty('redirectUrl'), $message);
    return $message;
}

// Was it cancelled? Mark transaction as such.
if ($result->isCancelled()) {
    $transaction->markCancelled();
    $transaction->log('Transaction marked as cancelled by the payment provider.', comTransactionLog::SOURCE_CHECKOUT);

    $order->log('commerce.log.cancelled_transaction', false, [
        'method' => $method->get('id'),
        'method_name' => $method->get('name'),
        'transaction' => $transaction->get('id')
    ]);

    $request->set('transaction', 0);
    $request->save();

    $message = $modx->getOption('messageCancelled', $scriptProperties, '<p>De betaling is geannuleerd. <a href="%link%">Probeer het opnieuw</a></p>');
    return str_replace('%link%', $selfLink, $message);
}

// Failed? Mark transaction as such.
if ($result->isFailed()) {
    $transaction->log('Transaction failed', comTransactionLog::SOURCE_CHECKOUT);
    $transaction->markFailed($result->getErrorMessage());
    $order->calculate();

    $errorKey = $newAttempt ? 'commerce.error_creating_transaction' : 'commerce.error_verifying_transaction';

    $order->log('commerce.log.failed_transaction', false, [
        'method' => $method->get('id'),
        'method_name' => $method->get('name'),
        'transaction' => $transaction->get('id'),
        'message' => $result->getErrorMessage()
    ]);

    $request->set('transaction', 0);
    $request->save();

    $message = $modx->getOption('messageFailed', $scriptProperties, '<p>' . $commerce->adapter->lexicon($errorKey, ['name' => $method->get('name'), 'message' => $result->getErrorMessage()]) . ' <a href="%link%">Probeer het opnieuw</a></p>');
    return str_replace('%link%', $selfLink, $message);
}


return '<p>Onbekende betaalstatus.</p>';