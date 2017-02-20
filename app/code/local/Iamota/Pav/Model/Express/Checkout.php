<?php

class Iamota_Pav_Model_Express_Checkout extends Mage_Paypal_Model_Express_Checkout
{
    /**
     * Update quote when returned from PayPal
     * rewrite billing address by paypal
     * save old billing address for new customer
     * export shipping address in case address absence
     *
     * @param string $token
     */
    public function returnFromPaypal($token)
    {
        $this->_getApi();
        $this->_api->setToken($token)
            ->callGetExpressCheckoutDetails();
        $quote = $this->_quote;

        // import shipping address
        $exportedShippingAddress = $this->_api->getExportedShippingAddress();

        if(!Mage::helper('pav')->paypalAddressValidation($exportedShippingAddress)){
            Mage::throwException(Mage::helper('pav')->__('P.O. Boxes are not allowed. Please use a valid address instead.'));
        }
        else{
            $this->_ignoreAddressValidation();
        }

        if (!$quote->getIsVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if ($shippingAddress) {
                if ($exportedShippingAddress) {
                    $this->_setExportedAddressData($shippingAddress, $exportedShippingAddress);

                    if ($quote->getPayment()->getAdditionalInformation(self::PAYMENT_INFO_BUTTON) == 1) {
                        // PayPal doesn't provide detailed shipping info: prefix, middlename, lastname, suffix
                        $shippingAddress->setPrefix(null);
                        $shippingAddress->setMiddlename(null);
                        $shippingAddress->setLastname(null);
                        $shippingAddress->setSuffix(null);
                    }

                    $shippingAddress->setCollectShippingRates(true);
                    $shippingAddress->setSameAsBilling(0);
                }

                // import shipping method
                $code = '';
                if ($this->_api->getShippingRateCode()) {
                    if ($code = $this->_matchShippingMethodCode($shippingAddress, $this->_api->getShippingRateCode())) {
                         // possible bug of double collecting rates :-/
                        $shippingAddress->setShippingMethod($code)->setCollectShippingRates(true);
                    }
                }
                $quote->getPayment()->setAdditionalInformation(
                    self::PAYMENT_INFO_TRANSPORT_SHIPPING_METHOD,
                    $code
                );
            }
        }

        // import billing address
        $portBillingFromShipping = $quote->getPayment()->getAdditionalInformation(self::PAYMENT_INFO_BUTTON) == 1
            && $this->_config->requireBillingAddress != Mage_Paypal_Model_Config::REQUIRE_BILLING_ADDRESS_ALL
            && !$quote->isVirtual();
        if ($portBillingFromShipping) {
            $billingAddress = clone $shippingAddress;
            $billingAddress->unsAddressId()
                ->unsAddressType();
            $data = $billingAddress->getData();
            $data['save_in_address_book'] = 0;
            $quote->getBillingAddress()->addData($data);
            $quote->getShippingAddress()->setSameAsBilling(1);
        } else {
            $billingAddress = $quote->getBillingAddress();
        }
        $exportedBillingAddress = $this->_api->getExportedBillingAddress();
        $this->_setExportedAddressData($billingAddress, $exportedBillingAddress);
        $billingAddress->setCustomerNotes($exportedBillingAddress->getData('note'));
        $quote->setBillingAddress($billingAddress);

        // import payment info
        $payment = $quote->getPayment();
        $payment->setMethod($this->_methodType);
        Mage::getSingleton('paypal/info')->importToPayment($this->_api, $payment);
        $payment->setAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_PAYER_ID, $this->_api->getPayerId())
            ->setAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_TOKEN, $token)
        ;
        $quote->collectTotals()->save();
    }

    /**
     * Make sure addresses will be saved without validation errors
     */
    private function _ignoreAddressValidation()
    {
        $this->_quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$this->_quote->getIsVirtual()) {
            $this->_quote->getShippingAddress()->setShouldIgnoreValidation(true);
            if (!$this->_config->requireBillingAddress && !$this->_quote->getBillingAddress()->getEmail()) {
                $this->_quote->getBillingAddress()->setSameAsBilling(1);
            }
        }
    }
}
