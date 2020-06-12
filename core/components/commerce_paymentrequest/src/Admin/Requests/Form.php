<?php

namespace modmore\Commerce_PaymentRequest\Admin\Requests;

use modmore\Commerce\Admin\Widgets\Form\HiddenField;
use modmore\Commerce\Admin\Widgets\FormWidget;

/**
 * Class Form
 * @package modmore\Commerce_PaymentRequest\Admin\Requests
 *
 * @property $record \prPaymentRequest
 */
class Form extends FormWidget
{
    protected $classKey = 'prPaymentRequest';
    public $key = 'shipping-methods-form';
    public $title = '';

    public function getFields(array $options = array())
    {
        $fields = [];

        $fields[] = new HiddenField($this->commerce, [
            'name' => 'id',
        ]);

        $fields[] = new HiddenField($this->commerce, [
            'name' => 'order',
        ]);

        $modelFields = $this->record->getModelFields();
        $fields = array_merge($fields, $modelFields);

        return $fields;
    }

    /**
     * @return bool
     */
    public function afterSave()
    {
        $this->record->set('reference', bin2hex(random_bytes(32)));
        $this->record->set('status', \prPaymentRequest::STATUS_WAITING);
        $this->record->set('created_on', time());
        if ($this->record->save()) {
            $this->record->send();
        }
        return true;
    }

    public function getFormAction(array $options = array())
    {
        if ($this->record->get('id')) {
            return $this->adapter->makeAdminUrl('order/paymentrequest/update', ['id' => $this->record->get('id'), 'order' => $this->record->get('order')]);
        }
        return $this->adapter->makeAdminUrl('order/paymentrequest/create', ['order' => $this->record->get('order')]);
    }
}