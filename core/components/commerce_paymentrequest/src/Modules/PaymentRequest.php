<?php
namespace modmore\Commerce_PaymentRequest\Modules;
use modmore\Commerce\Admin\Configuration\About\ComposerPackages;
use modmore\Commerce\Admin\Order\Overview;
use modmore\Commerce\Admin\Sections\SimpleSection;
use modmore\Commerce\Events\Admin\GeneratorEvent;
use modmore\Commerce\Events\Admin\PageEvent;
use modmore\Commerce\Events\MessagePlaceholders;
use modmore\Commerce\Modules\BaseModule;
use modmore\Commerce_PaymentRequest\Admin\Requests\Create;
use modmore\Commerce_PaymentRequest\Admin\Requests\Grid;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class PaymentRequest extends BaseModule {

    public function getName()
    {
        $this->adapter->loadLexicon('commerce_paymentrequest:default');
        return $this->adapter->lexicon('commerce_paymentrequest');
    }

    public function getAuthor()
    {
        return 'modmore';
    }

    public function getDescription()
    {
        return $this->adapter->lexicon('commerce_paymentrequest.description');
    }

    public function initialize(EventDispatcher $dispatcher)
    {
        // Load our lexicon
        $this->adapter->loadLexicon('commerce_paymentrequest:default');

        // Add the xPDO package, so Commerce can detect the derivative classes
        $root = dirname(__DIR__, 2);
        $path = $root . '/model/';
        $this->adapter->loadPackage('commerce_paymentrequest', $path);
        
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_PAGE_BEFORE_GENERATE, [$this, 'insertRequestsOnOrder']);
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_INIT_GENERATOR, [$this, 'initGenerator']);
        $dispatcher->addListener(\Commerce::EVENT_ORDER_MESSAGE_PLACEHOLDERS, [$this, 'addPlaceholdersToEmail']);

        // Add template path to twig
        $this->commerce->view()->addTemplatesPath($root . '/templates/');

        // Add composer libraries to the about section (v0.12+)
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_LOAD_ABOUT, [$this, 'addLibrariesToAbout']);
    }

    public function insertRequestsOnOrder(PageEvent $event)
    {
        $page = $event->getPage();
        if ($page->key === 'order') {
            /** @var Overview $page */
            $requests = new SimpleSection($this->commerce, [
                'title' => 'Payment Requests'
            ]);
            $requests->priority = 24; // before the transactionsection with priority 25
            $requests->addWidget((new Grid($this->commerce, [
                'order' => $page->getOption('order')
            ]))->setUp());

            $page->addSection($requests);
        }
    }

    public function initGenerator(GeneratorEvent $event)
    {
        $generator = $event->getGenerator();
        $generator->addPage('order/paymentrequest/create', Create::class);
    }

    public function addPlaceholdersToEmail(MessagePlaceholders $event)
    {
        $message = $event->getMessage();

        $requestId = (int)$message->getProperty('payment_request', 0);
        if ($requestId > 0) {
            $req = $this->adapter->getObject('prPaymentRequest', ['id' => $requestId]);
            if ($req) {
                $event->setPlaceholder('payment_request', $req->toArray());
            }
        }
    }

    public function addLibrariesToAbout(PageEvent $event)
    {
        $lockFile = dirname(__DIR__, 2) . '/composer.lock';
        if (file_exists($lockFile)) {
            $section = new SimpleSection($this->commerce);
            $section->addWidget(new ComposerPackages($this->commerce, [
                'lockFile' => $lockFile,
                'heading' => $this->adapter->lexicon('commerce.about.open_source_libraries') . ' - ' . $this->adapter->lexicon('commerce_paymentrequest'),
                'introduction' => '', // Could add information about how libraries are used, if you'd like
            ]));

            $about = $event->getPage();
            $about->addSection($section);
        }
    }

    public function getModuleConfiguration(\comModule $module)
    {
        $fields = [];

//        $fields[] = new DescriptionField($this->commerce, [
//            'description' => $this->adapter->lexicon('commerce_paymentrequest.module_description'),
//        ]);

        return $fields;
    }
}
