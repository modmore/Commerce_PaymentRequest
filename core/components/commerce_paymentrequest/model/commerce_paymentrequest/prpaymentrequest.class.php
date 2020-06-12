<?php

use modmore\Commerce\Admin\Widgets\Form\NumberField;
use modmore\Commerce\Admin\Widgets\Form\TextareaField;
use modmore\Commerce\Admin\Widgets\Form\Validation\Number;
use modmore\Commerce\Admin\Widgets\Form\Validation\Required;

/**
 * PaymentRequest for Commerce.
 *
 * Copyright 2020 by Mark Hamstra <mark@modmore.com>
 *
 * This file is meant to be used with Commerce by modmore. A valid Commerce license is required.
 *
 * @package commerce_paymentrequest
 * @license See core/components/commerce_paymentrequest/docs/license.txt
 */
class prPaymentRequest extends comSimpleObject
{
    public const STATUS_NEW = 'new';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_COMPLETED = 'completed';

    public function getModelFields()
    {
        $fields = parent::getModelFields();

        if ($this->get('status') !== self::STATUS_COMPLETED) {
            $fields[] = new NumberField($this->commerce, [
                'name' => 'amount',
                'label' => $this->adapter->lexicon('commerce.amount'),
                'validation' => [
                    new Required(),
                    new Number(0)
                ],
                'input_class' => 'commerce-field-currency',
            ]);

            $fields[] = new TextareaField($this->commerce, [
                'name' => 'note',
                'label' => $this->adapter->lexicon('commerce_paymentrequest.note'),
                'description' => $this->adapter->lexicon('commerce_paymentrequest.note.desc'),
            ]);
        }

        return $fields;
    }

    public function send()
    {
        /** @var comOrder $order */
        $order = $this->adapter->getObject('comOrder', ['id' => $this->get('order')]);
        if (!$order) {
            $this->adapter->log(1, '[Commerce_PaymentRequest] Could not send payment request ' . $this->get('id') . ' because order ' . $this->get('order') . ' could not be loaded.');
            return;
        }

        /** @var comOrderEmailMessage $msg */
        $msg = $this->adapter->newObject('comOrderEmailMessage');
        $msg->set('order', $this->get('order'));
        $msg->set('content', <<<TWIG
{% extends "emails/payment_request.twig" %}
TWIG
        );

        if ($ba = $order->getBillingAddress()) {
            $msg->set('recipient', $ba->get('email'));
        }
        $msg->set('created_on', time());
        if ($user = $this->adapter->getUser()) {
            $msg->set('created_by', $user->get('id'));
        }
        $msg->setProperties([
            'subject' => $this->adapter->lexicon('commerce_paymentrequest.subject', [
                'order' => $order->get('reference'),
                'amount' => $this->get('amount_formatted')
            ])
        ]);


        // Note the associated payment request - the module inserts the placeholders into the template based on this.
        $msg->setProperty('payment_request', $this->get('id'));

        if ($msg->save()) {
            $msg->send();
        }
    }

    public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
    {
        $fields =  parent::toArray($keyPrefix, $rawValues, $excludeLazy, $includeRelated);

        if (!$rawValues) {
            $fields['link'] = $this->adapter->makeResourceUrl(
                $this->adapter->getOption('commerce_paymentrequest.pay_resource'),
                '',
                [
                    'ref' => $this->get('reference')
                ]
            );
        }

        return $fields;
    }

}
