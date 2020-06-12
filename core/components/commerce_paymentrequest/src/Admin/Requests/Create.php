<?php

namespace modmore\Commerce_PaymentRequest\Admin\Requests;

use modmore\Commerce\Admin\Order\Overview;
use modmore\Commerce\Admin\Sections\SimpleSection;

class Create extends Overview {
    public $key = 'order/paymentrequest/create';
    public $title = 'commerce_paymentrequest.create';
    public static $permissions = ['commerce', 'commerce_order', 'commerce_order_transactions'];

    public function setUp()
    {
        $section = new SimpleSection($this->commerce, [
            'title' => $this->title
        ]);
        $section->addWidget((new Form($this->commerce, ['id' => 0, 'order' => $this->order->get('id')]))->setUp());
        $this->addSection($section);
        return $this;
    }
}