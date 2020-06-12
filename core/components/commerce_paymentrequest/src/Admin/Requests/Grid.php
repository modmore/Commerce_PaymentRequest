<?php

namespace modmore\Commerce_PaymentRequest\Admin\Requests;

use modmore\Commerce\Admin\Util\Column;
use modmore\Commerce\Admin\Widgets\GridWidget;

class Grid extends GridWidget {
    public $key = 'order-messages';
    public $defaultSort = 'created_on';

    public function getItems(array $options = array())
    {
        $items = [];

        $c = $this->adapter->newQuery('prPaymentRequest');
        $c->where([
            'order' => (int)$this->getOption('order', 0)
        ]);

        $sortby = array_key_exists('sortby', $options) && !empty($options['sortby']) ? $this->adapter->escape($options['sortby']) : $this->defaultSort;
        $sortdir = array_key_exists('sortdir', $options) && strtoupper($options['sortdir']) === 'DESC' ? 'DESC' : 'ASC';
        $c->sortby($sortby, $sortdir);

        $count = $this->adapter->getCount('prPaymentRequest', $c);
        $this->setTotalCount($count);

        $c->limit($options['limit'], $options['start']);
        /** @var \prPaymentRequest[] $collection */
        $collection = $this->adapter->getCollection('prPaymentRequest', $c);

        foreach ($collection as $status) {
            $items[] = $this->prepareItem($status);
        }

        return $items;
    }

    public function getColumns(array $options = array())
    {
        return [
            new Column('created_on', $this->adapter->lexicon('commerce.created_on'), true),
            new Column('amount_formatted', $this->adapter->lexicon('commerce.amount'), false),
            new Column('status', $this->adapter->lexicon('commerce.status'), true),
            new Column('completed_on', $this->adapter->lexicon('commerce.completed_on'), true, true),
        ];
    }

    public function getTopToolbar(array $options = array())
    {
        $toolbar = [];

        $addButton = [
            'name' => 'create-request',
            'title' => $this->adapter->lexicon('commerce_paymentrequest.create'),
            'type' => 'button',
            'link' => $this->adapter->makeAdminUrl('order/paymentrequest/create', ['order' => $this->getOption('order')]),
            'button_class' => 'commerce-ajax-modal',
            'icon_class' => 'cart',
            'modal_title' => $this->adapter->lexicon('commerce_paymentrequest.create'),
            'position' => 'top',
        ];

        $toolbar[] = $addButton;

        $toolbar[] = [
            'name' => 'limit',
            'title' => $this->adapter->lexicon('commerce.limit'),
            'type' => 'textfield',
            'value' => ((int)$options['limit'] === 10) ? '' : (int)$options['limit'],
            'position' => 'bottom',
            'width' => 'four wide',
        ];

        return $toolbar;
    }

    public function prepareItem(\prPaymentRequest $request)
    {
        $item = $request->toArray();
        $item['status'] = $this->adapter->lexicon('commerce_paymentrequest.status_' . $request->get('status'));

        $item['created_on'] = date('Y-m-d H:i:s', $item['created_on']);
        $item['completed_on'] = $item['completed_on'] > 0 ? date('Y-m-d H:i:s', $item['completed_on']) : '&mdash;';

        $item['actions'] = [];

//        $viewContentUrl = $this->adapter->makeAdminUrl('order/messages/view_content', ['id' => $item['id'], 'order' => $item['order']]);
//        $item['actions'][] = (new Action())
//            ->setUrl($viewContentUrl)
//            ->setTitle($this->adapter->lexicon('commerce.view_message_content'));
//
//        if (!$request->get('sent')) {
//            $sendUrl = $this->adapter->makeAdminUrl('order/messages/send', ['id' => $item['id'], 'order' => $item['order']]);
//            $item['actions'][] = (new Action())
//                ->setUrl($sendUrl)
//                ->setTitle($this->adapter->lexicon('commerce.order.send_message'));
//
//            $editUrl = $this->adapter->makeAdminUrl('order/messages/update', ['id' => $item['id'], 'order' => $item['order']]);
//            $item['actions'][] = (new Action())
//                ->setUrl($editUrl)
//                ->setTitle($this->adapter->lexicon('commerce.order.edit_message'));
//        }

        return $item;
    }
}