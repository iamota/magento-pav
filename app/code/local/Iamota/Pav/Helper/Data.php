<?php

class Iamota_Pav_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function paypalAddressValidation($address){
		/* insert any custom address validation logic here */
        return preg_match('/P\.?\s*O\.?\s*Bo?x?\b/i', $address->getData('street'))!==1 &&
            preg_match('/P\.?\s*O\.?\s*Bo?x?\b/i', $address->getData('street2'))!==1;
    }
}
