<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__.'/services/KintsugiApiService.php';

class Pskintsugitax extends Module
{
    public function __construct()
    {
        $this->name = 'pskintsugitax';
        $this->tab = 'billing_invoicing';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->need_instance = 0;

        parent::__construct();
        $this->displayName = $this->l('Kintsugi Sales Tax Override');
        $this->description = $this->l('Bypass PrestaShop native tax and use Kintsugi Sales Tax API.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionCartSave')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('displayShoppingCartFooter');
    }

    public function uninstall()
    {
        return Configuration::deleteByName('KINTSUGI_API_KEY')
            && Configuration::deleteByName('KINTSUGI_ORG_ID')
            && parent::uninstall();
    }

    public function getContent()
    {
        $output = '';
        // Save configuration
        if (Tools::isSubmit('submit_'.$this->name)) {
            Configuration::updateValue('KINTSUGI_API_KEY', Tools::getValue('KINTSUGI_API_KEY'));
            Configuration::updateValue('KINTSUGI_ORG_ID', Tools::getValue('KINTSUGI_ORG_ID'));
            $output .= $this->displayConfirmation($this->l('Configuration updated'));
        }
        $this->context->smarty->assign([
            'kintsugi_api_key' => Configuration::get('KINTSUGI_API_KEY', ''),
            'kintsugi_org_id' => Configuration::get('KINTSUGI_ORG_ID', '')
        ]);
        return $output . $this->display(__FILE__, 'views/templates/admin/config.tpl');
    }

    /**
     * Recalculate tax each time cart is saved.
     */
    public function hookActionCartSave($params)
    {
        if (empty($params['cart'])) {
            return;
        }
        $cart = $params['cart'];
        $customer = new Customer($cart->id_customer);
        $address = new Address($cart->id_address_delivery);

        $apiKey = Configuration::get('KINTSUGI_API_KEY');
        $orgId = Configuration::get('KINTSUGI_ORG_ID');

        if (!$apiKey || !$orgId) {
            return;
        }

        $service = new KintsugiApiService($apiKey, $orgId);
        $cartData = $this->buildCartData($cart);

        $shippingAddress = [
            'address1' => $address->address1,
            'city' => $address->city,
            'state' => State::getIsoById($address->id_state),
            'zip' => $address->postcode,
        ];

        try {
            $response = $service->estimateTax($cartData, $shippingAddress);
            // Store the tax amount in the cookie - for production consider storing in the cart or a custom table
            $this->context->cookie->__set('kintsugi_tax', $response['tax_amount']);
        } catch (Exception $e) {
            // Optionally, log error
            $this->context->cookie->__unset('kintsugi_tax');
        }
    }

    /**
     * Display Kintsugi tax in the cart/checkout
     */
    public function hookDisplayShoppingCartFooter($params)
    {
        if (!isset($this->context->cookie->kintsugi_tax)) {
            return '';
        }
        $tax = $this->context->cookie->kintsugi_tax;
        $this->context->smarty->assign('kintsugi_tax', $tax);
        return $this->display(__FILE__, 'views/templates/admin/cart_tax.tpl');
    }

    // Hook after order placement for transaction sync (not implemented in skeleton)
    public function hookActionValidateOrder($params)
    {
        // (For extension: you can POST to Kintsugi /v1/transactions after order confirmation)
    }

    public function buildCartData($cart)
    {
        // Get cart details and line_items for Kintsugi
        $products = $cart->getProducts();
        $items = [];
        $subtotal = 0;

        foreach ($products as $prod) {
            $items[] = [
                'product_id' => $prod['id_product'],
                'quantity' => $prod['cart_quantity'],
                'price' => (float)$prod['price']
            ];
            $subtotal += (float)$prod['price'] * $prod['cart_quantity'];
        }
        return [
            'items' => $items,
            'subtotal' => $subtotal
        ];
    }
}